<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncReservationBulkUpdateJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class ReservationBulkController extends Controller
{
    protected string $basePath;

    public function __construct()
    {
        $this->basePath = storage_path('app/data_sources/uploads');
    }

    public function index()
    {
        return view('admin.reservations-bulk', [
            'meta' => $this->readMeta(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'reservation_json' => 'required|string',
        ]);

        $json = trim((string) $request->string('reservation_json'));
        $payload = json_decode($json, true);
        if (! is_array($payload) || ! is_array($payload['reservations'] ?? null)) {
            return back()->with('message', 'Invalid payload. JSON must include reservations array.');
        }

        if (! File::exists($this->basePath)) {
            File::makeDirectory($this->basePath, 0755, true);
        }

        $payloadPath = $this->basePath . '/reservation_bulk.json';
        File::put($payloadPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->writeMeta([
            'uploaded_at' => now()->toDateTimeString(),
            'reservation_count' => count($payload['reservations']),
            'payload_path' => $payloadPath,
            'preview' => array_slice($payload['reservations'], 0, 3),
        ]);

        return back()->with('message', 'Reservation payload saved.');
    }

    public function run()
    {
        $meta = $this->readMeta();
        $payloadPath = $meta['payload_path'] ?? ($this->basePath . '/reservation_bulk.json');
        if (! File::exists($payloadPath)) {
            return back()->with('message', 'No reservation payload found. Save JSON first.');
        }

        SyncReservationBulkUpdateJob::dispatch($payloadPath);
        return back()->with('message', 'Reservation bulk update job dispatched.');
    }

    protected function readMeta(): ?array
    {
        $path = $this->basePath . '/reservation_bulk.meta.json';
        if (! File::exists($path)) {
            return null;
        }
        $json = json_decode(File::get($path), true);
        return is_array($json) ? $json : null;
    }

    protected function writeMeta(array $meta): void
    {
        $path = $this->basePath . '/reservation_bulk.meta.json';
        File::put($path, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
