<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Http\Requests\Concerns\ValidatesBreakPeriods;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Tests\TestCase;

/**
 * 休憩時間が勤務時間の範囲に収まっているかの検証。
 *
 * 夜勤は勤務が日付をまたぐため、時刻の文字列をそのまま比較すると
 * 深夜の休憩が範囲外と誤判定される。その扱いを重点的に確認する。
 */
class ValidatesBreakPeriodsTest extends TestCase
{
    /**
     * トレイトを検証するための最小のリクエスト
     */
    private function request(array $data): FormRequest
    {
        return new class($data) extends FormRequest
        {
            use ValidatesBreakPeriods;

            public function __construct(private array $payload)
            {
                parent::__construct();
            }

            public function input($key = null, $default = null): mixed
            {
                return $this->payload[$key] ?? $default;
            }

            /**
             * @return array<int, string>
             */
            public function errorsFor(): array
            {
                $validator = ValidatorFacade::make([], []);
                $this->validateBreakPeriodsWithinWork($validator);

                return $validator->errors()->all();
            }
        };
    }

    /**
     * @param  array<int, array{start?: string, end?: string}>  $breaks
     * @return array<int, string>
     */
    private function errors(?string $start, ?string $end, array $breaks): array
    {
        return $this->request([
            'work_start' => $start,
            'work_end' => $end,
            'break_periods' => $breaks,
        ])->errorsFor();
    }

    /**
     * @test
     */
    public function 日勤で範囲内の休憩は許可される(): void
    {
        $errors = $this->errors('09:00', '18:00', [['start' => '12:00', 'end' => '13:00']]);

        $this->assertSame([], $errors);
    }

    /**
     * @test
     */
    public function 日勤で範囲外の休憩は弾かれる(): void
    {
        $errors = $this->errors('09:00', '18:00', [['start' => '19:00', 'end' => '19:30']]);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('勤務時間の範囲外', $errors[0]);
    }

    /**
     * @test
     */
    public function 夜勤で日付をまたぐ休憩は許可される(): void
    {
        // 22:00〜06:00 の勤務における 02:00 の休憩は範囲内
        $errors = $this->errors('22:00', '06:00', [['start' => '02:00', 'end' => '03:00']]);

        $this->assertSame([], $errors);
    }

    /**
     * @test
     */
    public function 夜勤で勤務開始前の休憩は弾かれる(): void
    {
        // 22:00〜06:00 の勤務における 21:00 の休憩は範囲外
        $errors = $this->errors('22:00', '06:00', [['start' => '21:00', 'end' => '21:30']]);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('勤務時間の範囲外', $errors[0]);
    }

    /**
     * @test
     */
    public function 夜勤で勤務終了後の休憩は弾かれる(): void
    {
        // 22:00〜06:00 の勤務における 07:00 の休憩は範囲外
        $errors = $this->errors('22:00', '06:00', [['start' => '07:00', 'end' => '07:30']]);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('勤務時間の範囲外', $errors[0]);
    }

    /**
     * @test
     */
    public function 夜勤で日付をまたぐ休憩そのものも許可される(): void
    {
        // 23:00〜00:30 の休憩（日付をまたぐ）
        $errors = $this->errors('22:00', '06:00', [['start' => '23:00', 'end' => '00:30']]);

        $this->assertSame([], $errors);
    }

    /**
     * @test
     */
    public function 開始と終了が同じ休憩は弾かれる(): void
    {
        $errors = $this->errors('09:00', '18:00', [['start' => '12:00', 'end' => '12:00']]);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('同じ', $errors[0]);
    }

    /**
     * @test
     */
    public function 休憩が2枠とも範囲内なら許可される(): void
    {
        $errors = $this->errors('22:00', '06:00', [
            ['start' => '23:30', 'end' => '00:00'],
            ['start' => '03:00', 'end' => '03:30'],
        ]);

        $this->assertSame([], $errors);
    }

    /**
     * @test
     */
    public function 勤務時間が片方しかない場合は判定しない(): void
    {
        // 退勤忘れなど、勤務時間が片方だけの状態でも登録を妨げない
        $errors = $this->errors('09:00', null, [['start' => '12:00', 'end' => '13:00']]);

        $this->assertSame([], $errors);
    }

    /**
     * @test
     */
    public function 休憩が未入力の場合は判定しない(): void
    {
        $errors = $this->errors('09:00', '18:00', []);

        $this->assertSame([], $errors);
    }
}
