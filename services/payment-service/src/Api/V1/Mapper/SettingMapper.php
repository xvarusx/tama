<?php

declare(strict_types=1);

namespace App\Api\V1\Mapper;

use App\Api\V1\Documentation\SettingAttributesDocument;
use App\Api\V1\Documentation\SettingDocument;
use App\Api\V1\Resource\SettingResource;
use App\Entity\Setting;

/** Entity -> API v1 contract. A V2 mapper can reshape the same entities differently. */
final class SettingMapper
{
    public function toResource(Setting $setting): SettingResource
    {
        return new SettingResource(
            key: $setting->getKey(),
            attributes: [
                'value' => $setting->getValue(),
                'type' => $setting->getType(),
                'description' => $setting->getDescription(),
            ],
        );
    }

    public function toDocument(Setting $setting): SettingDocument
    {
        return new SettingDocument(
            key: $setting->getKey(),
            attributes: new SettingAttributesDocument(
                value: $setting->getValue(),
                type: $setting->getType(),
                description: $setting->getDescription(),
            ),
        );
    }
}