<?php

declare(strict_types=1);

namespace App\Media;

use App\Entity\Product;
use App\Entity\ProductMedia;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Stores product images outside the database. The DB keeps a path relative to
 * the upload directory, so the directory can be moved or mounted as a volume.
 */
final readonly class MediaStorage
{
    /** @var array<string, string> */
    public const array ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];

    public const MAX_BYTES = 5 * 1024 * 1024;

    public function __construct(
        #[Autowire(param: 'media_dir')]
        private string $uploadDir,
        private Filesystem $filesystem,
    ) {}

    public function store(UploadedFile $file, Product $product, int $position): ProductMedia
    {
        $mime = (string)$file->getMimeType();
        $extension = self::ALLOWED_MIME_TYPES[$mime] ?? 'bin';

        $relativePath = sprintf(
            '%d/%s.%s',
            $product->getId(),
            bin2hex(random_bytes(16)),
            $extension,
        );

        $directory = dirname($this->absolutePathFor($relativePath));
        if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create the media directory "%s".', $directory));
        }

        $file->move($directory, basename($relativePath));

        return new ProductMedia($relativePath, $mime, $position);
    }

    public function delete(ProductMedia $media): void
    {
        $path = $this->absolutePath($media);
        if (is_file($path)) {
            $this->filesystem->remove($path);
        }

        // Remove the per-product directory once it is empty.
        $directory = dirname($path);
        if (is_dir($directory)) {
            $entries = scandir($directory);
            if ($entries === false) {
                throw new RuntimeException(sprintf('Unable to read the media directory "%s".', $directory));
            }

            if (\count($entries) === 2 && !rmdir($directory)) {
                throw new RuntimeException(sprintf('Unable to remove the media directory "%s".', $directory));
            }
        }
    }

    public function absolutePath(ProductMedia $media): string
    {
        return $this->absolutePathFor((string)$media->getPath());
    }

    private function absolutePathFor(string $relativePath): string
    {
        if (preg_match('#\A\d+/[a-f0-9]{32}\.(?:jpg|png|webp|svg|bin)\z#', $relativePath) !== 1) {
            throw new InvalidArgumentException('Invalid product media path.');
        }

        return rtrim($this->uploadDir, '/') . '/' . $relativePath;
    }
}
