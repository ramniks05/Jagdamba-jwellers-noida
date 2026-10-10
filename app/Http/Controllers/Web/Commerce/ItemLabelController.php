<?php

namespace App\Http\Controllers\Web\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Setting;
use App\Services\Commerce\JewelleryLabelZplService;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ItemLabelController extends Controller
{
    public const BATCH_LIMIT = 100;

    public function show(Item $item, JewelleryLabelZplService $labels): View
    {
        $this->authorize('view', $item);
        $this->authorize('inventory.print');

        return view('commerce.items.label', $this->screen($labels, $item) + ['item' => $item]);
    }

    public function testPreview(JewelleryLabelZplService $labels): View
    {
        $this->authorize('inventory.print');

        return view('commerce.items.label', $this->screen($labels, null) + ['item' => null]);
    }

    public function zpl(Request $request, Item $item, JewelleryLabelZplService $labels): Response
    {
        $this->authorize('view', $item);
        $this->authorize('inventory.print');

        return $this->plain($labels->generate($item, ['copies' => $request->query('copies', 1)]));
    }

    public function testZpl(Request $request, JewelleryLabelZplService $labels): Response
    {
        $this->authorize('inventory.print');

        return $this->plain($labels->generateTest(['copies' => $request->query('copies', 1)]));
    }

    public function batch(Request $request, JewelleryLabelZplService $labels): JsonResponse
    {
        $this->authorize('inventory.print');

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:'.self::BATCH_LIMIT],
            'items.*' => ['required', 'uuid', 'distinct'],
            'copies' => ['nullable', 'integer', 'min:1'],
        ], [
            'items.max' => 'Print at most '.self::BATCH_LIMIT.' tags at a time.',
        ]);

        $labels->resolve(['copies' => $data['copies'] ?? 1]);
        $found = Item::query()->with('purity')->whereIn('uuid', $data['items'])->get()->keyBy('uuid');
        $tags = [];
        $errors = [];

        foreach ($data['items'] as $uuid) {
            $item = $found->get($uuid);

            if (! $item || ! $request->user()->can('view', $item)) {
                $errors[] = ['uuid' => $uuid, 'code' => null, 'message' => 'This piece was not found in this shop.'];

                continue;
            }

            try {
                $tags[] = ['uuid' => $uuid, 'code' => $item->item_code, 'zpl' => $labels->generate($item, ['copies' => $data['copies'] ?? 1])];
            } catch (ValidationException $exception) {
                $errors[] = ['uuid' => $uuid, 'code' => $item->item_code, 'message' => collect($exception->errors())->flatten()->first()];
            }
        }

        return response()->json(['tags' => $tags, 'errors' => $errors], 200, ['Cache-Control' => 'no-store']);
    }

    /**
     * @return array<string, mixed>
     */
    private function screen(JewelleryLabelZplService $labels, ?Item $item): array
    {
        $preview = null;
        $problem = null;

        try {
            $preview = $labels->preview($item);
        } catch (ValidationException $exception) {
            $problem = collect($exception->errors())->flatten()->first();
        }

        $saved = $labels->saved();

        return [
            'preview' => $preview,
            'problem' => $problem,
            'previewQr' => $preview && $preview['code']['format'] === 'qr'
                ? (new QRCode(new QROptions(['outputBase64' => true, 'scale' => 4, 'addQuietzone' => false])))->render($preview['payload'])
                : null,
            'printerName' => (string) $saved['printer_name'],
            'maxCopies' => (int) $saved['max_copies'],
            'canManageSettings' => auth()->user()->can('update', Setting::class),
        ];
    }

    private function plain(string $zpl): Response
    {
        return response($zpl, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
