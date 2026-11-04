<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use App\Services\StampLocation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * スタッフ画面の打刻リクエスト
 *
 * 位置情報（latitude / longitude / accuracy）は付けても付けなくてもよい。
 * 位置情報はあくまで参考の記録なので、値がおかしくても打刻自体は止めず、その位置情報だけを使わない。
 */
class StampRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'latitude' => ['nullable'],
            'longitude' => ['nullable'],
            'accuracy' => ['nullable'],
        ];
    }

    /**
     * 送られてきた位置情報。無い場合や、緯度・経度として正しくない場合はnull
     */
    public function location(): ?StampLocation
    {
        $latitude = $this->input('latitude');
        $longitude = $this->input('longitude');

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        $latitude = (float) $latitude;
        $longitude = (float) $longitude;
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return null;
        }

        $accuracy = $this->input('accuracy');

        return new StampLocation(
            $latitude,
            $longitude,
            is_numeric($accuracy) && $accuracy >= 0 ? (int) round((float) $accuracy) : null,
        );
    }
}
