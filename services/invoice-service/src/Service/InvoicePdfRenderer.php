<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Invoice;
use DateTimeImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

final readonly class InvoicePdfRenderer
{
    public function __construct(
        private Environment $twig,
        #[Autowire(param: 'kernel.project_dir')]
        private string $projectDir,
        #[Autowire(param: 'kernel.cache_dir')]
        private string $cacheDir,
    ) {}

    /** @return string raw PDF bytes */
    public function render(Invoice $invoice): string
    {
        $html = $this->twig->render('invoice/pdf.html.twig', [
            'invoice' => $invoice,
            'lines' => $invoice->getLines(),
            'total' => $invoice->getTotal(),
            'creditedAmount' => $invoice->getCreditedAmount(),
            'balance' => $invoice->getBalance(),
            'isDraft' => $invoice->getNumber() === null,
            'now' => new DateTimeImmutable(),
        ]);

        return $this->pdf($html);
    }

    private function pdf(string $html): string
    {
        $tempDir = $this->cacheDir . '/dompdf';
        if (!is_dir($tempDir) && !mkdir($tempDir, 0775, true) && !is_dir($tempDir)) {
            throw new RuntimeException(sprintf('Could not create the dompdf temp directory "%s".', $tempDir));
        }

        $options = new Options();
        $options->setChroot($this->projectDir);
        $options->setTempDir($tempDir);
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }
}
