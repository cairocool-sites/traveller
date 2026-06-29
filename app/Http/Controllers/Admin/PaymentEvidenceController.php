<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentEvidence;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class PaymentEvidenceController extends Controller
{
    public function show(PaymentEvidence $evidence)
    {
        abort_unless(auth()->check(), 403);
        Gate::authorize('viewEvidence', $evidence->payment);

        abort_unless(Storage::disk('local')->exists($evidence->file_path), 404);

        return Storage::disk('local')->download($evidence->file_path, $evidence->original_name);
    }
}
