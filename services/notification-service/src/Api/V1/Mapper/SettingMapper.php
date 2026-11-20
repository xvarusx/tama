<?php

declare(strict_types=1);

namespace App\Api\V1\Mapper;

use App\Api\V1\Resource\SettingResource;
use App\Entity\Setting;

final class SettingMapper
{
    public function toResource(Setting $setting): SettingResource
    {
        return new SettingResource(
            key: $setting->getKey(),
            value: $setting->getValue(),
            description: $setting->getDescription(),
        );
    }
}
