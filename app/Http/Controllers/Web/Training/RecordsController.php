<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Training;

use App\Domain\Access\Models\User;
use App\Domain\Training\Actions\AttachExternalCertificate;
use App\Domain\Training\Actions\EnrolInsteadOfExternal;
use App\Domain\Training\Actions\RevokeCertificate;
use App\Domain\Training\Models\Certificate;
use App\Domain\Training\Models\Course;
use App\Domain\Training\Models\ExternalTraining;
use App\Domain\Training\Support\CertificatePdf;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Certificates and external training on someone's record (D103–D105): the certificate PDF,
 * revoking one, the certificate from an external course, and meeting a training request with a
 * catalog course instead.
 */
final class RecordsController extends Controller
{
    public function certificate(Certificate $certificate, CertificatePdf $pdf, #[CurrentUser] User $user): StreamedResponse
    {
        abort_unless(Gate::allows('training.viewCertificate', $certificate) && $certificate->revoked_at === null, 404);
        activity('training')->causedBy($user)->performedOn($certificate)->event('downloaded')->log('Certificate downloaded');

        return Storage::disk(Certificate::DISK)->download($pdf->path($certificate), "{$certificate->serial}.pdf");
    }

    public function revoke(Request $request, Certificate $certificate, RevokeCertificate $revoke, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless(Gate::allows('training.revokeCertificate', $certificate), 404);
        $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $revoke->handle(Actor::user($user), $certificate, $request->string('reason')->toString());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('The certificate was revoked.')]);

        return back();
    }

    public function upload(Request $request, ExternalTraining $record, AttachExternalCertificate $attach, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless(Gate::allows('training.updateExternal', $record), 404);
        $request->validate(['file' => ['required', 'file', 'max:10240', 'mimetypes:application/pdf,image/jpeg,image/png,image/webp']]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $attach->handle(Actor::user($user), $record, $file);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Certificate uploaded.')]);

        return back();
    }

    public function download(ExternalTraining $record): StreamedResponse
    {
        abort_unless(Gate::allows('training.viewExternal', $record) && $record->certificate_path !== null, 404);

        return Storage::disk(ExternalTraining::DISK)->download((string) $record->certificate_path, $record->certificate_name ?? basename((string) $record->certificate_path));
    }

    public function link(Request $request, ExternalTraining $record, EnrolInsteadOfExternal $link, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless(Gate::allows('training.linkExternal', $record), 404);
        $request->validate(['course' => ['required', 'string', 'size:26', 'exists:courses,ulid']]);
        $link->handle(Actor::user($user), $record, Course::query()->where('ulid', $request->string('course')->toString())->firstOrFail());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Enrolled in the catalog course.')]);

        return back();
    }
}
