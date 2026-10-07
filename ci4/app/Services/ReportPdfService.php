<?php

namespace App\Services;

use CodeIgniter\HTTP\ResponseInterface;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * One place that turns a report into a PDF.
 *
 * Remittance, Overdue and Commission are rendered for all three roles that can
 * see them (System Admin, Branch Admin, Staff) through the same header partial
 * (Views/reports/_pdf_header.php), layout (Views/reports/pdf/_layout.php) and
 * stylesheet (public/assets/css/report-pdf.css), so they read as one family.
 *
 * "Print" serves the PDF inline (browser PDF viewer, opened in a new tab);
 * "Export PDF" serves the same bytes as an attachment.
 */
class ReportPdfService
{
    /** type => [title, body view, paper orientation, filename prefix] */
    private const TYPES = [
        'remittance' => ['Remittance Report', 'reports/pdf/remittance', 'landscape', 'remittance_report'],
        'overdue'    => ['Overdue Report', 'reports/pdf/overdue', 'landscape', 'overdue_report'],
        'commission' => ['Commission Report', 'reports/pdf/commission', 'portrait', 'commission_report'],
        'client_statement' => ['Client Statement', 'reports/pdf/client_statement', 'portrait', 'client_statement'],
    ];

    /**
     * @param string   $type     remittance | overdue | commission | client_statement
     * @param array    $data     body view data (rows, totals, ...)
     * @param array    $context  branch_name (''=all branches), date_from/date_to (either may be null
     *                           for "as of today" reports), filter_lines (string[])
     * @param bool     $inline   true = Content-Disposition inline (Print), false = attachment
     */
    public function respond(ResponseInterface $response, string $type, array $data, array $context, bool $inline): ResponseInterface
    {
        [$title, $bodyView, $orientation, $prefix] = self::TYPES[$type];

        $meta = [
            'title'        => $title,
            'branch_line'  => ($context['branch_name'] ?? '') !== '' ? (string) $context['branch_name'] : 'All branches',
            'period_line'  => $this->periodLine($context['date_from'] ?? null, $context['date_to'] ?? null),
            'filter_lines' => $context['filter_lines'] ?? [],
            'generated_by' => $this->generatedBy(),
            'generated_at' => date('j M Y, g:i A'),
            'logo_path'    => $this->logoPath(),
        ];

        $html = view('reports/pdf/_layout', [
            'meta'        => $meta,
            'orientation' => $orientation,
            'css_path'    => FCPATH . 'assets/css/report-pdf.css',
            'body'        => view($bodyView, $data),
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        // The stylesheet and logo are local files under public/.
        $options->setChroot([FCPATH]);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();

        $this->stampPageNumbers($dompdf);

        $filename = $prefix . '_' . date('Ymd_His') . '.pdf';

        return $response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . $filename . '"')
            ->setHeader('Cache-Control', 'private, no-store')
            ->setBody($dompdf->output());
    }

    private function stampPageNumbers(Dompdf $dompdf): void
    {
        $canvas = $dompdf->getCanvas();
        $font   = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $width  = $canvas->get_width();
        $height = $canvas->get_height();

        $canvas->page_text($width - 90, $height - 26, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 7, [0.35, 0.35, 0.35]);
        $canvas->page_text(34, $height - 26, 'Kaagapay Mo Karamay Funeral Homes Co. - Confidential', $font, 7, [0.35, 0.35, 0.35]);
    }

    private function periodLine(?string $from, ?string $to): string
    {
        $from = trim((string) $from);
        $to   = trim((string) $to);

        if ($from !== '' && $to !== '') {
            return date('j M Y', strtotime($from)) . ' to ' . date('j M Y', strtotime($to));
        }

        return 'As of ' . date('j M Y');
    }

    private function generatedBy(): string
    {
        $name = trim((string) session('first_name') . ' ' . (string) session('last_name'));
        $role = (string) session('role_name');

        if ($name === '') {
            $name = 'System';
        }

        return $role !== '' ? $name . ' (' . $role . ')' : $name;
    }

    /** First company-logo file found under public/assets/images, or null for the placeholder box. */
    private function logoPath(): ?string
    {
        foreach (['company-logo.png', 'company-logo.jpg', 'company-logo.jpeg'] as $file) {
            $path = FCPATH . 'assets/images/' . $file;
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
