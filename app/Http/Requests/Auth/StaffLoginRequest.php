<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffLoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_code' => ['required', 'string'],
            'employee_code' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'company_code' => '会社コード',
            'employee_code' => '個人コード',
            'password' => 'パスワード',
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $company = Company::query()
            ->where('company_code', $this->string('company_code'))
            ->first();

        if (! $company) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'company_code' => '会社コードが正しくありません。',
            ]);
        }

        $user = User::query()
            ->where('employee_code', $this->string('employee_code'))
            ->whereHas('companies', fn ($query) => $query->where('companies.id', $company->id))
            ->first();

        if (! $user || ! Hash::check($this->string('password'), $user->password)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'employee_code' => '個人コードまたはパスワードが正しくありません。',
            ]);
        }

        // 別ガード（admin）のセッションが残っているとInertia側でユーザー情報が
        // 混在するため、明示的にログアウトしてからstaffへログインする。
        if (Auth::guard('admin')->check()) {
            Auth::guard('admin')->logout();
        }

        Auth::guard('staff')->login($user, $this->boolean('remember'));

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'company_code' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(
            'staff|'.$this->string('company_code').'|'.$this->string('employee_code').'|'.$this->ip()
        );
    }
}
