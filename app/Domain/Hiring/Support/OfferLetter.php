<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Support;

use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Hiring\Data\OfferInputData;
use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Models\Offer;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\EmploymentType;
use App\Domain\Organization\Models\Position;
use App\Domain\People\Models\Employee;
use App\Domain\Setup\Support\Settings;
use App\Support\Money\Money;
use App\Support\Templates\SafeTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

/**
 * Offer letters (D95): one template per language in Hiring settings with `{{ variable }}` chips,
 * rendered through SafeTemplate into the offer's editable text, then laid out on the company
 * letterhead for Gotenberg. The letter follows the candidate's language.
 */
final readonly class OfferLetter
{
    /** Variables the templates may use. */
    public const array VARIABLES = [
        'candidate_name', 'job_title', 'start_date', 'salary', 'branch', 'department', 'position',
        'employment_type', 'manager', 'company', 'expires_on', 'today',
    ];

    public function __construct(private Settings $settings, private SafeTemplate $templates) {}

    public function template(string $locale): string
    {
        $saved = $this->settings->get('hiring.offer_letter');

        return FieldSpec::text($saved, $locale, self::defaults()[$locale === 'ar' ? 'ar' : 'en']);
    }

    /**
     * The letter text for an offer's terms, in the offer's language.
     */
    public function render(Offer $offer, Candidate $candidate, ?string $template = null): string
    {
        $locale = $offer->locale;

        return $this->templates->render($template ?? $this->template($locale), $this->variables($offer, $candidate), $locale, escapeHtml: false);
    }

    /**
     * The letter for terms not saved yet (the offer dialog's "fill from template").
     */
    public function preview(Candidate $candidate, OfferInputData $input): string
    {
        $offer = new Offer([
            'locale' => $candidate->locale === 'ar' ? 'ar' : 'en',
            'job_title' => $input->jobTitle,
            'start_on' => $input->startOn,
            'expires_on' => $input->expiresOn,
        ]);
        $offer->salary = new Money($input->salary['amount_minor'], $input->salary['currency']);
        $offer->setRelation('branch', Branch::query()->where('ulid', strtolower($input->branchId))->first());
        $offer->setRelation('department', $input->departmentId === null ? null : Department::query()->where('ulid', strtolower($input->departmentId))->first());
        $offer->setRelation('position', $input->positionId === null ? null : Position::query()->where('ulid', strtolower($input->positionId))->first());
        $offer->setRelation('employmentType', $input->employmentTypeId === null ? null : EmploymentType::query()->where('ulid', strtolower($input->employmentTypeId))->first());
        $offer->setRelation('manager', $input->managerId === null ? null : Employee::query()->where('ulid', strtolower($input->managerId))->first());

        return $this->render($offer, $candidate);
    }

    /**
     * @return array<string, string|null>
     */
    public function variables(Offer $offer, Candidate $candidate): array
    {
        $locale = $offer->locale;

        return [
            'candidate_name' => $candidate->name,
            'job_title' => $offer->job_title,
            'start_date' => $offer->start_on->toDateString(),
            'salary' => $offer->salary instanceof Money ? $offer->salary->format($locale === 'ar' ? 'ar' : 'en') : null,
            'branch' => Texts::one($offer->branch, 'name', $locale),
            'department' => Texts::one($offer->department, 'name', $locale),
            'position' => Texts::one($offer->position, 'title', $locale),
            'employment_type' => Texts::one($offer->employmentType, 'name', $locale),
            'manager' => $offer->manager?->displayName($locale),
            'company' => $this->company($locale),
            'expires_on' => $offer->expires_on?->toDateString(),
            'today' => CarbonImmutable::now()->toDateString(),
        ];
    }

    /**
     * The printable letter: letterhead, the text, the terms and, once accepted, the signature.
     *
     * @param  array{name: string, at: CarbonImmutable, image: string}|null  $signature  image is PNG bytes
     */
    public function html(Offer $offer, Candidate $candidate, ?array $signature = null): string
    {
        $locale = $offer->locale;
        $rtl = $locale === 'ar';
        $vars = $this->variables($offer, $candidate);
        $logo = $this->logoDataUri();
        $label = fn (string $en, string $ar): string => e($rtl ? $ar : $en);
        $row = fn (string $en, string $ar, ?string $value): string => $value === null || $value === '' ? '' : '<tr><th>'.$label($en, $ar).'</th><td>'.e($value).'</td></tr>';
        $paragraphs = implode('', array_map(
            fn (string $p): string => '<p>'.nl2br(e(trim($p))).'</p>',
            array_filter(preg_split('/\n{2,}/', str_replace("\r\n", "\n", $offer->letter)) ?: [], fn (string $p): bool => trim($p) !== ''),
        ));
        $signed = $signature === null ? '' : '<div class="signature"><img alt="" src="data:image/png;base64,'.base64_encode($signature['image']).'"><div><strong>'.e($signature['name']).'</strong></div><div class="muted">'
            .$label('Accepted electronically on', 'وُقّع إلكترونيًا في').' '.e($signature['at']->toIso8601String()).'</div></div>';

        return '<!doctype html><html lang="'.$locale.'" dir="'.($rtl ? 'rtl' : 'ltr').'"><head><meta charset="utf-8"><style>'
            .'body{font-family:"IBM Plex Sans Arabic","Noto Sans Arabic","IBM Plex Sans","Noto Sans",Arial,sans-serif;color:#182238;font-size:12pt;line-height:1.7;margin:0}'
            .'header{display:flex;align-items:center;justify-content:space-between;border-bottom:2px solid #4570C0;padding-bottom:12px;margin-bottom:24px}'
            .'header img{max-height:48px;max-width:220px}header .company{font-size:16pt;font-weight:600}'
            .'table{border-collapse:collapse;margin:16px 0;width:100%}th,td{text-align:start;padding:6px 8px;border-bottom:1px solid #DCE3EE;vertical-align:top}th{width:35%;color:#5E6A82;font-weight:500}'
            .'.signature{margin-top:32px}.signature img{max-height:80px;display:block;margin-bottom:4px}.muted{color:#5E6A82;font-size:10pt}'
            .'</style></head><body><header>'.($logo !== null ? '<img alt="" src="'.$logo.'">' : '<span class="company">'.e($vars['company'] ?? '').'</span>')
            .'<span class="muted">'.e(CarbonImmutable::now()->toDateString()).'</span></header>'
            .$paragraphs
            .'<table>'
            .$row('Position', 'الوظيفة', $vars['job_title'])
            .$row('Start date', 'تاريخ المباشرة', $vars['start_date'])
            .$row('Monthly salary', 'الراتب الشهري', $vars['salary'])
            .$row('Location', 'مكان العمل', $vars['branch'])
            .$row('Department', 'القسم', $vars['department'])
            .$row('Reports to', 'المدير المباشر', $vars['manager'])
            .$row('Offer valid until', 'العرض صالح حتى', $vars['expires_on'])
            .'</table>'.$signed.'</body></html>';
    }

    /**
     * @return array{en: string, ar: string}
     */
    public static function defaults(): array
    {
        return [
            'en' => "Dear {{ candidate_name }},\n\nWe are delighted to offer you the position of {{ job_title }} at {{ company }}, based at {{ branch }}. Your start date will be {{ start_date | date }}, and your monthly salary will be {{ salary }}.\n\nThis offer is valid until {{ expires_on | date }}. To accept, sign below.\n\nWe look forward to welcoming you to the team.\n\n{{ company }}",
            'ar' => "عزيزي/عزيزتي {{ candidate_name }}،\n\nيسعدنا أن نقدّم لك عرضًا لوظيفة {{ job_title }} في {{ company }}، ومقرّها {{ branch }}. يبدأ عملك في {{ start_date | date }}، وراتبك الشهري {{ salary }}.\n\nهذا العرض صالح حتى {{ expires_on | date }}. للقبول، وقّع أدناه.\n\nنتطلع إلى انضمامك إلى فريقنا.\n\n{{ company }}",
        ];
    }

    private function company(string $locale): string
    {
        return FieldSpec::text($this->settings->get('company.name'), $locale, FieldSpec::string(config('app.name'), 'Mawred'));
    }

    private function logoDataUri(): ?string
    {
        $path = $this->settings->get('company.logo_path');

        if (! is_string($path) || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) Storage::disk('public')->get($path));
    }
}
