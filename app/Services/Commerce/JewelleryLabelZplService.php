<?php

namespace App\Services\Commerce;

use App\Models\Company;
use App\Models\Item;
use App\Services\Foundation\NumberFormatService;
use App\Services\Foundation\SettingService;
use App\Support\CompanyContext;
use Illuminate\Validation\ValidationException;

/**
 * Fixed 56 x 13 mm dumbbell jewellery tag for a 203 dpi Zebra printer: two printable flaps of
 * about 20 x 13 mm with the fold between them, laid out like the shop's reference tag.
 *
 * Coordinates are in printer dots (8 per mm). They are initial values, not measurements, until a
 * printed test tag has been checked against the real tag and its fold.
 */
class JewelleryLabelZplService
{
    /**
     * Printable ASCII without the ZPL control characters ^ and ~, no leading or trailing space.
     */
    public const PAYLOAD_PATTERN = '/^[\x21-\x5D\x5F-\x7D](?:[\x20-\x5D\x5F-\x7D]{0,62}[\x21-\x5D\x5F-\x7D])?$/';

    public const TEST_CODE = 'TEST-0001';

    public const DPI = 203;

    public const WIDTH = 448;

    public const HEIGHT = 104;

    /**
     * First flap, 0-20 mm: piece code, purity and weights.
     */
    public const LEFT_PANEL = [0, 159];

    /**
     * 20-36 mm, where the tag folds round the piece. Nothing is printed here.
     */
    public const FOLD = [160, 287];

    /**
     * Second flap, 36-56 mm: piece type, shop name and the QR code, kept wholly inside this flap.
     */
    public const RIGHT_PANEL = [288, 447];

    /**
     * Space kept from the outer ends and the top and bottom of the tag.
     */
    public const MARGIN = 8;

    /**
     * Space kept from the fold on each flap.
     */
    public const FOLD_SIDE = 4;

    /**
     * Gap between the shop name and the QR code.
     */
    private const CODE_GAP = 4;

    /**
     * Average advance of ZPL font 0 as a share of its width setting. Kept on the generous side so text never overruns.
     */
    private const CHAR_FACTOR = 0.62;

    private const MIN_FONT_WIDTH = 10;

    private const MAX_QR_MODULE = 3;

    public function __construct(
        private readonly SettingService $settings,
        private readonly NumberFormatService $format,
        private readonly CompanyContext $context,
    ) {}

    /**
     * @param  array<string, mixed>  $settings  Overrides for the saved tag settings, plus copies.
     */
    public function generate(Item $item, array $settings = []): string
    {
        $resolved = $this->resolve($settings);

        return $this->render($this->layout($this->itemFields($item, $resolved)), $resolved);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function generateTest(array $settings = []): string
    {
        $resolved = $this->resolve($settings);

        return $this->render($this->layout($this->testFields()), $resolved);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function preview(?Item $item, array $settings = []): array
    {
        $resolved = $this->resolve($settings);
        $fields = $item ? $this->itemFields($item, $resolved) : $this->testFields();

        return $this->layout($fields) + ['settings' => $resolved];
    }

    /**
     * Saved tag settings for the current shop, keyed without the "label." prefix.
     *
     * @return array<string, mixed>
     */
    public function saved(?Company $company = null): array
    {
        $company ??= $this->context->company();
        $values = [];

        foreach (array_keys($this->catalog()) as $key) {
            $values[substr($key, 6)] = $this->settings->get($key, $company);
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{printer_name: string, barcode_payload: string, max_copies: int, copies: int}
     */
    public function resolve(array $overrides = []): array
    {
        $copies = $overrides['copies'] ?? 1;
        $saved = $this->saved();
        $values = array_merge($saved, array_intersect_key($overrides, $saved));
        $this->validate($values);

        $resolved = [
            'printer_name' => (string) $values['printer_name'],
            'barcode_payload' => (string) $values['barcode_payload'],
            'max_copies' => (int) $values['max_copies'],
        ];

        if (filter_var($copies, FILTER_VALIDATE_INT) === false || (int) $copies < 1 || (int) $copies > $resolved['max_copies']) {
            throw ValidationException::withMessages([
                'copies' => 'Copies must be a whole number from 1 to '.$resolved['max_copies'].'.',
            ]);
        }

        return $resolved + ['copies' => (int) $copies];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function payloadFor(Item $item, array $settings): string
    {
        $barcode = trim((string) $item->barcode);
        $payload = $settings['barcode_payload'] === 'barcode_or_code' && $barcode !== ''
            ? $barcode
            : trim((string) $item->item_code);

        if ($payload === '') {
            throw ValidationException::withMessages(['payload' => 'This piece has no code to print.']);
        }

        if (! preg_match(self::PAYLOAD_PATTERN, $payload)) {
            throw ValidationException::withMessages([
                'payload' => 'The code "'.mb_strimwidth($payload, 0, 40, '…').'" has characters a tag cannot hold. Use letters, numbers and simple symbols, at most 64 characters, and no ^ or ~.',
            ]);
        }

        return $payload;
    }

    public static function mmToDots(float $mm, int $dpi = self::DPI): int
    {
        return (int) round($mm * $dpi / 25.4);
    }

    /**
     * Encodes text for a ^FH field: anything outside a safe printable set becomes _XX hex bytes (UTF-8 with ^CI28).
     */
    public static function escape(string $text): string
    {
        $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text) ?? '';
        $out = '';

        foreach (str_split($text) as $byte) {
            $out .= preg_match('/[A-Za-z0-9 .,:\/()+\-#@&*%=]/', $byte) ? $byte : sprintf('_%02X', ord($byte));
        }

        return $out;
    }

    /**
     * Squares on one side of a QR code (error level M) big enough for the payload.
     */
    public static function qrModules(string $payload): int
    {
        $length = strlen($payload);

        foreach ([21 => 14, 25 => 26, 29 => 42, 33 => 62] as $size => $capacity) {
            if ($length <= $capacity) {
                return $size;
            }
        }

        return 37;
    }

    /**
     * Estimated printed width in dots of text in font 0 at the given width setting.
     */
    public static function textWidth(string $text, int $fontWidth): int
    {
        return (int) ceil(mb_strlen($text) * $fontWidth * self::CHAR_FACTOR);
    }

    /**
     * Splits a name like "Jagdamba Jewellers" over two balanced lines.
     *
     * @return list<string>
     */
    public static function splitInTwo(string $text): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];

        if (count($words) < 2) {
            return [trim($text)];
        }

        $best = [implode(' ', $words)];
        $bestLength = PHP_INT_MAX;

        for ($cut = 1; $cut < count($words); $cut++) {
            $lines = [implode(' ', array_slice($words, 0, $cut)), implode(' ', array_slice($words, $cut))];
            $longest = max(mb_strlen($lines[0]), mb_strlen($lines[1]));

            if ($longest < $bestLength) {
                [$best, $bestLength] = [$lines, $longest];
            }
        }

        return $best;
    }

    /**
     * @param  array{payload: string, code: string, type: ?string, shop: ?string, weights: list<string>}  $fields
     * @return array<string, mixed>
     */
    public function layout(array $fields): array
    {
        $warnings = [];

        $leftX = self::LEFT_PANEL[0] + self::MARGIN;
        $leftRoom = self::LEFT_PANEL[1] + 1 - self::FOLD_SIDE - $leftX;
        $texts = [
            $this->line($fields['code'], $leftX, 10, 22, 19, $leftRoom, null, true),
        ];

        foreach (array_values($fields['weights']) as $index => $weight) {
            $texts[] = $this->line($weight, $leftX, 40 + $index * 28, 21, 17, $leftRoom, null, true);
        }

        $modules = self::qrModules($fields['payload']);
        $module = min(self::MAX_QR_MODULE, intdiv(self::HEIGHT - 2 * self::MARGIN, $modules));

        if ($module < 2) {
            throw ValidationException::withMessages([
                'payload' => 'The code "'.mb_strimwidth($fields['payload'], 0, 40, '…').'" is too long for this tag. Use a shorter barcode or print the piece code.',
            ]);
        }

        $codeSize = $modules * $module;
        $codeX = self::RIGHT_PANEL[1] + 1 - self::MARGIN - $codeSize;
        $codeY = intdiv(self::HEIGHT - $codeSize, 2);

        $columnX = self::RIGHT_PANEL[0] + self::FOLD_SIDE;
        $columnWidth = $codeX - self::CODE_GAP - $columnX;

        if ($fields['type'] !== null) {
            $type = $this->fitWords($fields['type'], 16, $columnWidth, $warnings);
            $texts[] = $this->line($type, $columnX, 12, 20, 16, $columnWidth, $columnWidth, false, $warnings);
        }

        foreach (array_values($fields['shop'] !== null ? self::splitInTwo($fields['shop']) : []) as $index => $part) {
            $texts[] = $this->line($part, $columnX, 44 + $index * 27, 23, 17, $columnWidth, $columnWidth, false, $warnings);
        }

        return [
            'width' => self::WIDTH,
            'height' => self::HEIGHT,
            'fold' => self::FOLD,
            'left_panel' => self::LEFT_PANEL,
            'right_panel' => self::RIGHT_PANEL,
            'payload' => $fields['payload'],
            'code' => ['x' => $codeX, 'y' => $codeY, 'size' => $codeSize, 'format' => 'qr', 'module' => $module],
            'texts' => $texts,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * @param  array<string, mixed>  $layout
     * @param  array<string, mixed>  $settings
     */
    public function render(array $layout, array $settings): string
    {
        $code = $layout['code'];

        $zpl = [
            '^XA',
            '^CI28',
            '^PW'.$layout['width'],
            '^LL'.$layout['height'],
            '^LH0,0',
            '^FO'.$code['x'].','.$code['y'].'^BQN,2,'.$code['module'].'^FDMA,'.$layout['payload'].'^FS',
        ];

        foreach ($layout['texts'] as $text) {
            $block = $text['block'] !== null ? '^FB'.$text['block'].',1,0,C' : '';
            $zpl[] = '^FO'.$text['x'].','.$text['y'].'^A0N,'.$text['height'].','.$text['font_width'].$block.'^FH^FD'.self::escape($text['text']).'^FS';
        }

        $zpl[] = '^PQ'.$settings['copies'].',0,1,Y';
        $zpl[] = '^XZ';

        return implode("\n", $zpl)."\n";
    }

    /**
     * Places one line, narrowing the font until it fits the room. Left panel text must never be cut.
     *
     * @param  list<string>  $warnings
     * @return array{x: int, y: int, height: int, font_width: int, text: string, width: int, block: ?int}
     */
    private function line(string $text, int $x, int $y, int $height, int $fontWidth, int $room, ?int $block, bool $strict, array &$warnings = []): array
    {
        while ($fontWidth > self::MIN_FONT_WIDTH && self::textWidth($text, $fontWidth) > $room) {
            $fontWidth--;
        }

        if (self::textWidth($text, $fontWidth) > $room) {
            if ($strict) {
                throw ValidationException::withMessages([
                    'payload' => '"'.$text.'" is too long for the tag. Shorten the piece code.',
                ]);
            }

            while ($text !== '' && self::textWidth($text, $fontWidth) > $room) {
                $text = rtrim(mb_substr($text, 0, -1));
            }

            $warnings[] = 'Some text was shortened to fit the tag.';
        }

        return [
            'x' => $x,
            'y' => $y,
            'height' => $height,
            'font_width' => $fontWidth,
            'text' => $text,
            'width' => $block ?? self::textWidth($text, $fontWidth),
            'block' => $block,
        ];
    }

    /**
     * Drops whole trailing words from a piece type that would not fit, so "Diamond Jewellery" becomes "Diamond".
     *
     * @param  list<string>  $warnings
     */
    private function fitWords(string $text, int $fontWidth, int $room, array &$warnings): string
    {
        while (self::textWidth($text, $fontWidth) > $room && str_contains($text, ' ')) {
            $text = rtrim(mb_substr($text, 0, (int) mb_strrpos($text, ' ')));
            $warnings[] = 'Some text was shortened to fit the tag.';
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{payload: string, code: string, type: ?string, shop: ?string, weights: list<string>}
     */
    private function itemFields(Item $item, array $settings): array
    {
        $item->loadMissing(['purity', 'category']);
        $purity = trim((string) ($item->purity?->code ?: $item->purity?->name));
        $type = trim((string) ($item->category?->name ?: $item->name));

        return [
            'payload' => $this->payloadFor($item, $settings),
            'code' => trim($item->item_code.($purity !== '' ? '  '.$purity : '')),
            'type' => $type !== '' ? mb_strtolower($type) : null,
            'shop' => $this->shopName(),
            'weights' => [
                'G. Wt : '.$this->weight((string) ($item->gross_weight ?? '0')),
                'N. Wt. : '.$this->weight((string) ($item->net_weight ?? '0')),
            ],
        ];
    }

    /**
     * @return array{payload: string, code: string, type: ?string, shop: ?string, weights: list<string>}
     */
    private function testFields(): array
    {
        return [
            'payload' => self::TEST_CODE,
            'code' => self::TEST_CODE.'  22K',
            'type' => 'test',
            'shop' => $this->shopName(),
            'weights' => ['G. Wt : '.$this->weight('1.234'), 'N. Wt. : '.$this->weight('1.234')],
        ];
    }

    private function shopName(): ?string
    {
        $name = trim((string) $this->context->company()?->name);

        return $name !== '' ? $name : null;
    }

    private function weight(string $grams): string
    {
        return preg_replace('/ g$/', ' gm', $this->format->weight($grams, $this->context->company())) ?? $grams;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function validate(array $values): void
    {
        $data = [];
        $rules = [];
        $names = [];

        foreach ($this->catalog() as $key => $meta) {
            $short = substr($key, 6);
            $data[$short] = $values[$short];
            $rules[$short] = $meta['rules'];
            $names[$short] = strtolower($meta['label']);
        }

        validator($data, $rules, [], $names)->validate();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function catalog(): array
    {
        return array_filter(
            config('foundation.settings'),
            fn (array $meta, string $key) => str_starts_with($key, 'label.'),
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
