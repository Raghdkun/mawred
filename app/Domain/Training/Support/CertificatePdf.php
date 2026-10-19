<?php

declare(strict_types=1);

namespace App\Domain\Training\Support;

use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Setup\Support\CompanyBrand;
use App\Domain\Training\Models\Certificate;
use App\Support\Pdf\Gotenberg;
use App\Support\Qr\QrSvg;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

/**
 * The certificate as a PDF (D110): the design's frame artwork on A4 landscape with the company,
 * holder, course, dates, serial and a QR to the verification page (D104). Arabic mirrors the
 * text; the artwork, logo and QR keep their places (the frame has a fixed slot for the QR) and
 * the serial stays left-to-right. Printed once and kept.
 */
final readonly class CertificatePdf
{
    private const string FRAME = 'design/illustrations/current/svg/light/mw-certificate-frame.svg';

    public function __construct(private Gotenberg $gotenberg, private CompanyBrand $brand) {}

    /** The stored PDF's path, printing it first if needed. */
    public function path(Certificate $certificate): string
    {
        if ($certificate->pdf_path !== null && Storage::disk(Certificate::DISK)->exists($certificate->pdf_path)) {
            return $certificate->pdf_path;
        }

        $path = "training/certificates/{$certificate->ulid}.pdf";
        Storage::disk(Certificate::DISK)->put($path, $this->gotenberg->html($this->html($certificate), landscape: true, margin: '0'));
        $certificate->forceFill(['pdf_path' => $path])->save();

        return $path;
    }

    public function html(Certificate $certificate): string
    {
        $locale = $certificate->locale === 'ar' ? 'ar' : 'en';
        $rtl = $locale === 'ar';
        $e = fn (string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $label = fn (string $en, string $ar): string => $e($rtl ? $ar : $en);
        $date = fn (?CarbonImmutable $day): string => $day?->settings(['locale' => $locale])->isoFormat('LL') ?? '';
        $frame = is_file(base_path(self::FRAME)) ? 'data:image/svg+xml;base64,'.base64_encode((string) file_get_contents(base_path(self::FRAME))) : null;
        $verify = route('certificates.verify', $certificate->serial);
        $logo = $this->brand->logoDataUri();
        $company = $this->brand->name($locale);
        $holder = FieldSpec::text($certificate->getTranslations('holder'), $locale);
        $course = FieldSpec::text($certificate->getTranslations('title'), $locale);
        $start = $rtl ? 'right' : 'left';
        $end = $rtl ? 'left' : 'right';

        // The root stays left-to-right (a fixed-width RTL root can scroll the page out of the print
        // area); the certificate itself is mirrored for Arabic.
        return '<!doctype html><html lang="'.$locale.'" dir="ltr"><head><meta charset="utf-8"><style>'
            .'@page{size:A4 landscape;margin:0}html,body{margin:0;padding:0}'
            .'body{font-family:"IBM Plex Sans Arabic","Noto Sans Arabic","IBM Plex Sans","Noto Sans",Arial,sans-serif;color:#182238}'
            .'.page{position:relative;width:1123px;height:794px;overflow:hidden;'.($frame !== null ? 'background:url('.$frame.') no-repeat 0 0/1123px 794px;' : 'background:#F4F8FD;').'}'
            .'.abs{position:absolute}.center{left:0;right:0;text-align:center}'
            .'.company{top:104px;left:110px;max-width:340px}.company img{max-height:52px;max-width:220px;display:block}'
            .'.qr{top:100px;right:110px;width:88px;height:88px}.qr img{width:88px;height:88px;display:block}'
            .'.kicker{top:205px;font-size:14px;letter-spacing:.18em;text-transform:uppercase;color:#5E6A82}'
            .'.title{top:232px;font-size:34px;font-weight:700;color:#2E5AA8}'
            .'.lead{top:318px;font-size:16px;color:#5E6A82}'
            .'.holder{top:398px;font-size:38px;font-weight:600}'
            .'.done{top:488px;font-size:16px;color:#5E6A82}'
            .'.course{top:514px;font-size:24px;font-weight:600;padding:0 160px}'
            .'.date{top:660px;width:210px;font-size:13px;text-align:center;color:#5E6A82}.date strong{display:block;font-size:15px;color:#182238;font-weight:600}'
            .'.issued{'.$start.':150px}.expires{'.$end.':150px}'
            .'.serial{top:740px;font-size:11px;color:#5E6A82;direction:ltr;unicode-bidi:isolate}'
            .'</style></head><body><div class="page" dir="'.($rtl ? 'rtl' : 'ltr').'">'
            .($logo !== null ? '<div class="abs company"><img alt="" src="'.$logo.'"></div>' : '')
            .'<div class="abs qr" dir="ltr"><img alt="" src="'.QrSvg::dataUri($verify, 176).'"></div>'
            .'<div class="abs center kicker">'.$e($company).'</div>'
            .'<div class="abs center title">'.$label('Certificate of completion', 'شهادة إتمام').'</div>'
            .'<div class="abs center lead">'.$label('This certifies that', 'نشهد بأن').'</div>'
            .'<div class="abs center holder">'.$e($holder).'</div>'
            .'<div class="abs center done">'.$label('has completed the course', 'أتمّ بنجاح دورة').'</div>'
            .'<div class="abs center course">'.$e($course).'</div>'
            .'<div class="abs date issued">'.$label('Issued on', 'تاريخ الإصدار').'<strong>'.$e($date($certificate->issued_on)).'</strong></div>'
            .'<div class="abs date expires">'.$label('Valid until', 'صالحة حتى').'<strong>'.$e($certificate->expires_on === null ? ($rtl ? 'لا تنتهي' : 'Does not expire') : $date($certificate->expires_on)).'</strong></div>'
            .'<div class="abs center serial">'.$e($certificate->serial).' · '.$e($verify).'</div>'
            .'</div></body></html>';
    }
}
