<?php

declare(strict_types=1);

namespace App\Handler\Setting;

use App\Api\V1\Request\UpdateSettingRequest;
use App\Entity\Setting;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Exception\RuntimeException;

/**
 * Use case: update or create a setting.
 */
final readonly class UpdateSettingHandler
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(UpdateSettingRequest $request): Setting
    {
        $setting = $this->em->find(Setting::class, $request->key);

        if ($setting === null) {
            $setting = new Setting(
                key: $request->key,
                value: $request->value,
                type: 'STRING',
                description: $request->description,
            );
            $this->em->persist($setting);
        } else {
            $setting->setValue($request->value);
            if ($request->description !== null) {
                $setting->setDescription($request->description);
            }
        }

        $this->em->wrapInTransaction(function () use ($setting): void {
            $this->em->persist($setting);
            $this->em->flush();
        });

        return $setting;
    }
}