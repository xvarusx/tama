<?php

namespace App\Serializer;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Resolves a category id (e.g. "3") from the request payload to an existing
 * Category entity instead of letting the object normalizer create a blank one.
 */
final class CategoryDenormalizer implements DenormalizerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): ?Category
    {
        $id = is_array($data) ? ($data['id'] ?? null) : $data;

        if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
            throw new NotNormalizableValueException(sprintf('A category id is expected, "%s" given.', get_debug_type($id)));
        }

        $category = $this->entityManager->getRepository(Category::class)->find((int) $id);

        if (null === $category) {
            throw new NotNormalizableValueException(sprintf('Category with id "%s" was not found.', $id));
        }

        return $category;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        if (Category::class !== $type) {
            return false;
        }

        if (is_int($data) || (is_string($data) && ctype_digit($data))) {
            return true;
        }

        return is_array($data) && array_keys($data) === ['id'];
    }

    public function getSupportedTypes(?string $format): array
    {
        // Support depends on the data, so the supportsDenormalization() result is not cacheable.
        return [Category::class => false];
    }
}
