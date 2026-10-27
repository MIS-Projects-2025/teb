<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// TEB: test the mail settings, e.g. php artisan teb:mail-test you@astigp.com
Artisan::command('teb:mail-test {to}', function (string $to) {
    $this->line('Mailbox: ' . config('mail.mailers.smtp.username') . '  From: ' . config('mail.from.address'));
    try {
        Illuminate\Support\Facades\Mail::raw('TEB Portal mail test.', fn($m) => $m->to($to)->subject('TEB mail test'));
        $this->info("Sent to {$to}.");
    } catch (Throwable $e) {
        $this->error($e->getMessage());
    }
})->purpose('Send a test email with the current mail settings');

// TEB: rebuild the approval section of projects still For Approval with the current rules
// (one signature per department). Signatures already given on those projects are cleared.
// php artisan teb:reroute            -> all projects For Approval
// php artisan teb:reroute 2026-00003 -> one TEB No.
Artisan::command('teb:reroute {teb_no?}', function (?string $tebNo = null) {
    $projects = App\Models\Teb\TebProject::where('status', App\Models\Teb\TebProject::STATUS_FOR_APPROVAL)
        ->when($tebNo, fn($q) => $q->where('teb_no', $tebNo))->get();

    foreach ($projects as $p) {
        try {
            app(App\Services\Teb\TebWorkflow::class)->routeForApproval($p);
            $this->info("{$p->teb_no}: " . $p->approvals()->pluck('department_group')->implode(' | '));
        } catch (Illuminate\Validation\ValidationException $e) {
            $this->error("{$p->teb_no}: " . collect($e->errors())->flatten()->implode(' '));
        }
    }
    $this->line($projects->count() . ' project(s) checked.');
})->purpose('Rebuild approval sections of projects For Approval');
