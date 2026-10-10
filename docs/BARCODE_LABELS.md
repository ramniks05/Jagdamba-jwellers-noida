# Jewellery barcode tags (Zebra ZD230 + QZ Tray)

Tags print from the browser at the counter straight to the Zebra printer on that
computer. The shop server only builds the ZPL text; QZ Tray, running on the
counter PC, hands it to the Windows printer as raw data. The server never talks
to the USB printer and no relay server is used.

```
Browser (item page) --fetch ZPL--> Laravel (auth, shop check, inventory.print)
Browser --raw ZPL--> QZ Tray (localhost websocket) --> Windows spooler --> ZD230
```

## Where things are

| Piece | File |
| --- | --- |
| ZPL builder | `app/Services/Commerce/JewelleryLabelZplService.php` |
| Tag pages and ZPL endpoints | `app/Http/Controllers/Web/Commerce/ItemLabelController.php` |
| Tag settings page | `app/Http/Controllers/Web/Commerce/LabelSettingController.php`, `resources/views/commerce/labels/settings.blade.php` |
| QZ certificate and signing | `app/Http/Controllers/Web/Commerce/QzSigningController.php`, `config/services.php` (`qz`) |
| Browser printing | `public/js/jewellery-label-printer.js` |
| QZ Tray client library | `public/vendor/qz-tray/qz-tray.js` (official npm `qz-tray` 2.3.0, LGPL-2.1, unmodified) |
| Settings catalog | `config/foundation.php`, keys `label.*` |
| Tests | `tests/Feature/Commerce/JewelleryLabelTest.php` |
| Scan lookup on a bill | `app/Services/Commerce/BillScanService.php`, `app/Http/Controllers/Web/Commerce/BillScanController.php` |
| Scan box on the bill page | `resources/views/commerce/sales/create.blade.php` (Scan tag card) |
| Scan tests | `tests/Feature/Commerce/BillScanTest.php` |

## Routes

All sit behind `auth` and `company.context`. A piece from another shop returns 404
(the shop scope on `Item`). Every tag route also needs the `inventory.print`
permission (owner, manager, inventory manager, auditor). Saving tag settings
needs `settings.manage`.

| Method | Path | Name | Returns |
| --- | --- | --- | --- |
| GET | `/items/{item}/barcode-preview` | `items.label` | Preview and print page |
| GET | `/items/{item}/barcode-zpl?copies=N` | `items.label.zpl` | `text/plain; charset=UTF-8` ZPL |
| GET | `/jewellery/barcode-test` | `labels.test` | Test tag page, no piece needed |
| GET | `/jewellery/barcode-test-zpl?copies=N` | `labels.test.zpl` | Test tag ZPL |
| POST | `/jewellery/barcode-zpl/batch` | `labels.batch` | JSON `{tags: [{uuid, code, zpl}], errors: [{uuid, code, message}]}`, up to 100 pieces |
| GET/POST | `/jewellery/barcode-settings` | `labels.settings.edit` / `.update` | Tag settings |
| GET | `/jewellery/qz/certificate` | `labels.qz.certificate` | Public certificate, or 204 when not set up |
| POST | `/jewellery/qz/sign` | `labels.qz.sign` | Base64 SHA512 signature, or 404 when not set up |

Errors come back as JSON 422 (bad copies, code that cannot be printed, tag
settings that do not fit) when the request sends `Accept: application/json`,
which the printing script does.

## What is printed

The tag is fixed in code to match the shop's reference tag. There is no layout screen.

**Size.** These are **initial dimensions, not measured on a real tag**. Check them with a test print.

- Whole tag: 56 × 13 mm at 203 dpi (8 dots per mm), so `^PW448` and `^LL104`.
- Two usable flaps of about 20 × 13 mm each, with a fold between them.

The layout follows the shop's old tag (`apps06.com` PDF).

| Area | Dots (x) | Millimetres | Content |
| --- | --- | --- | --- |
| Left flap | 0–159 (text 8–155) | 0–20 | Code and purity at y 10, `G. Wt :` at y 40, `N. Wt. :` at y 68 |
| Fold | 160–287 | 20–36 | Nothing, always blank |
| Right flap, name column | 292–372 | 36–46.5 | Piece type at y 12, shop name lines at y 44 and 71, centred |
| Right flap, QR code | 377–439 | 47–55 | 3 dots per square, y 20–82 |

```
| GSE2741  20K     |      fold      |   set     [QR] |
| G. Wt : 4.610 gm |    (blank)     | Jagdamba  [QR] |
| N. Wt : 4.610 gm |                | Jewellers [QR] |
```

All coordinates are constants in `JewelleryLabelZplService` (`WIDTH`, `HEIGHT`, `LEFT_PANEL`, `FOLD`,
`RIGHT_PANEL`, `MARGIN`, `FOLD_SIDE`) and the fixed `^FO` positions in `layout()`.

- Text and code stay 1 mm (8 dots) from the outer edges and 4 dots from the fold.
- A test checks that nothing is printed on the fold or outside its flap.
- A test also checks that the QR code sits entirely inside the right flap.

- **Left flap** (text x 8–155):
  - Piece code and purity are printed in `^A0N,22,19`.
  - Weights are printed in `^A0N,21,w`.
  - If a line would be too wide, the font is narrowed, down to width 10. Left flap text is never cut.
  - A piece code that is too long even then is refused with a message.
- **Right flap, name column** (x 292–372):
  - **Piece type:** lower case, like the reference ("set"). It comes from the category, or from the piece name when there is no category.
  - **Shop name:** comes from the shop profile and is never hard coded. It is split over two lines, both centred in the column.
  - A piece type too long for the column loses whole trailing words, and the preview warns.
- **QR code:**
  - Sits right-aligned in the right flap and centred vertically, entirely inside that flap so a scanner can read it flat.
  - Uses 3 dots per square, so a code of up to 14 characters is 63 dots (about 8 mm).
  - Longer codes drop to 2 dots per square when 3 would not fit in the flap. The name column narrows to match.

**Code type.** The reference tag has three corner squares, which is a QR code. This uses `^BQN,2,m` with error
level M. **Not yet checked:** what the old tag's QR holds. Scan an old tag with a phone. If it holds the
piece code (e.g. `GSE2741`), the default below matches it.

**What the code holds.** By default this is the piece's existing barcode if it has
one, else its piece code. Old tags keep scanning to the same piece.
"Piece code only" can be chosen instead. Scanning types the value into the search
box on a new bill or the pieces list, which already match code and barcode.
Values are checked against
`JewelleryLabelZplService::PAYLOAD_PATTERN` (printable ASCII, no `^` or `~`,
1–64 characters). A piece whose barcode breaks the rule is refused with a
message; it is never silently replaced.

**Text safety.** Text fields use `^FH` with `^CI28`. Anything outside a small safe
set (including `^`, `~`, `_` and non-English letters) is sent as `_XX` UTF-8
hex bytes, so a piece name cannot inject printer commands.

**ZPL commands used.**
- `^XA` / `^XZ` start and end the tag.
- `^CI28` selects UTF-8.
- `^PW448` / `^LL104` set the tag size.
- `^LH0,0` sets the label home.
- `^FO` sets field origins.
- `^BQN,2,m` with `^FDMA,` prints the QR code.
- `^A0N,h,w` is the scalable font.
- `^FB` centres the right-panel text.
- `^FH` hex-escapes text.
- `^PQn,0,1,Y` sets copies.

No printer settings are sent: no media type (`^MN`), darkness (`~SD`), speed (`^PR`) or saving (`^JU`).
The fold is part of the tag, not a media gap, so the printer's gap calibration is left as it is.
Set darkness and speed on the printer or in the ZDesigner driver if needed.

**Copies.** The number of copies is 1 up to the "Most copies in one print" setting (default 20).
The page asks for confirmation at 10 or more. Batch printing is limited to 100 pieces.

Printing never changes stock, status or any record. A test checks this.

## Tag settings

Under Settings → Barcode tags, or `/jewellery/barcode-settings`. The settings are:
- Printer name
- Most copies in one print
- What the code holds

Size and layout are fixed in code.

## Adjusting after the first physical test (provisional coordinates)

The 56 × 13 mm size, the 20 mm left panel and the fold at 20–24 mm are **design assumptions until a
printed tag has been folded and checked**. If the real tag differs:

- **Whole print shifted:** change `^LH0,0` in `render()` to e.g. `^LH8,0` (moves right 1 mm), or
  adjust the `^FO` values.
- **Fold in a different place:** change `LEFT_PANEL`, `FOLD` and `RIGHT_PANEL`, plus the right panel x
  values in `layout()`. Then run `php artisan test --filter=JewelleryLabelTest`; the fold test will
  catch anything that now overlaps.
- **Different tag length:** change `WIDTH` / `HEIGHT`, which set `^PW` / `^LL`.
- **QR slightly low on paper:** some Zebra firmware adds a few dots above `^BQ` codes. Lower the QR y if the
  bottom is clipped.

## Counter PC setup (Windows)

1. Install the Zebra ZD230 driver (ZDesigner) from zebra.com. Confirm the printer
   appears in **Settings → Bluetooth & devices → Printers & scanners** as
   `ZDesigner ZD230-203dpi ZPL`, or whatever it is called there.
2. Load tag stock and run the printer's media calibration (SmartCal) as shown in
   the ZD230 user guide. It should feed a few tags and stop on a tag edge.
3. Install QZ Tray from https://qz.io/download/ and start it. A QZ icon sits in
   the system tray. It must be running whenever tags are printed. Set it to start
   with Windows from its tray menu.
4. Open the shop website in Chrome or Edge on that PC and sign in.
5. Open **Settings → Barcode tags**. Type the printer name exactly as Windows shows
   it. Save.

## First print

1. On the tag settings page, click **Print a test tag**.
2. The status line should say `Ready. QZ Tray found <printer>.` If it says QZ Tray
   is not running, start QZ Tray. If the printer is not found, fix the name.
3. Click **Print test tag**. Without signing set up, QZ Tray asks to allow the
   website: choose **Allow**. Optionally tick "Remember this decision".
4. Measure the printed tag and fold it:
   - **Fold position:** the blank strip should sit exactly on the fold (20–24 mm from the left edge).
   - **Edges:** nothing should be cut off at the edges.
   - **Off-position print:** follow "Adjusting after the first physical test" above.
   - **Faint print:** raise darkness on the printer or in the ZDesigner driver preferences.
   - **Blank tags, or printing across tag edges:** recalibrate the printer (SmartCal).
5. Scan the code. It should type `TEST-0001`.
6. Open any piece, click **Print tag**, check the preview and print one copy. Scan
   it. It should open or find that piece.

"Tag sent to …" means QZ Tray handed the job to Windows. It does not prove the
printer printed it, so check the tag.

## Silent printing (signing) — optional, recommended for daily use

Without signing, QZ Tray shows an allow prompt. With signing, it trusts the shop
server and prints without prompting.

1. Get a certificate:
   - **Self-signed:** in QZ Tray, go to Advanced → Site Manager → **+** → Create new.
     This makes `digital-certificate.txt` and `private-key.pem`. QZ Tray trusts it
     only on PCs where it was created or imported.
   - **Trusted:** buy a QZ Tray certificate from QZ Industries, which works on any PC.
2. Copy both files to the server **outside `public/`**, for example
   `/home/<user>/qz/`, readable only by the site's PHP user (`chmod 600`).
3. In the server's `.env` (never in git):
   ```
   QZ_CERTIFICATE_PATH=/home/<user>/qz/digital-certificate.txt
   QZ_PRIVATE_KEY_PATH=/home/<user>/qz/private-key.pem
   QZ_PRIVATE_KEY_PASSPHRASE=
   ```
   Then run `php artisan config:clear`, and `php artisan config:cache` if config is cached.
4. With a self-signed certificate, import `digital-certificate.txt` into QZ Tray's
   Site Manager on each counter PC.
5. Reload the tag page and print. There should be no prompt.

The private key stays on the server. The browser only receives the public
certificate and per-request signatures from `POST /jewellery/qz/sign`, which
needs a signed-in user with print rights and is rate limited.
QZ Tray's own security checks stay on.


## Scanning tags on a bill (T-6900 scanner)

The scanner is treated as a **USB keyboard (HID)**: it types the code it reads and
normally presses Enter after it. No scanner SDK or driver is used, and the server
never talks to the scanner.

**Checked with the shop's T-6900 on 10 Oct 2026 (local copy of the app):**

- It works as a USB keyboard and sends Enter after each code, with no setup changes.
- It reads QR codes: both the old apps06.com tag and a generated QR for `G22-006`, each shown on a phone screen.
- Scanning `G22-006` on New bill added the piece. The old tag `GSE2741` gave "No piece in this shop has the code…", because that piece is not in the new app yet.
- Codes shown on the same PC's screen did not reach the bill, because the image viewer took keyboard focus. Show test codes on a phone or on paper.
- Not yet checked: a tag printed on the ZD230, and a full bill saved from scans.

**How it works**

- The bill page (`/sales/create`) opens with the cursor in the **Scan tag** box.
- A scan, or a code typed by hand, is sent on **Enter**. If the scanner is set to add **Tab** instead, that works too. Pressing **Add** does the same for typing by hand.
- No timing guesswork is used, so a slow typist and a fast scanner behave the same.
- The box clears after each scan and stays focused, so the next tag can be scanned straight away.
- The page asks `GET /sales/scan?code=…` (`sales.scan`, needs `sales.create`, rate limited to 300 a minute). The lookup only reads; it never changes the piece, its stock or its status.
- **Lookup order:**
  1. An exact match on the piece's barcode.
  2. Then an exact match on the piece code.
  3. Then the same two ignoring upper and lower case.
- Control characters a scanner may add (CR, LF, Tab, STX) are removed. Only pieces in your own shop are searched (shop scope on `Item`).
- A found piece is added to **This bill** with the same price the page already uses (`SaleService::quote`). Totals, discount, GST, round off and credit are recalculated by the page's existing code, without a page reload.
- Saving still goes through `SaleService::post`, which locks the pieces and refuses any that are no longer Available. Two counters cannot sell the same piece.

| Answer | Shown to the cashier |
| --- | --- |
| 200 | Green: "Added G22-006 · 22K ring · Gold 22K · Net 12.220 g · ₹ …" |
| 404 | Red: No piece in this shop has the code "…" (nothing is created) |
| 409 | Red: "… is sold / reserved / repair …, so it cannot be billed." |
| 422 | Red: no rate for that metal and purity today, or the scan is not a tag code |
| Same piece again | Amber: "… is already on this bill." (no second line) |

**Double scans.** A code that is still being looked up is ignored if it arrives again. A piece that is already
on the bill is not added twice. The server also rejects a bill that lists the same piece twice.

**Other fields are not affected.** Only the Scan tag box listens for scans; there is no page-wide key listener.
Enter inside the other boxes on the bill (discount, payment, note) no longer saves the bill by accident. A scan
into the wrong box cannot submit an invoice. Use the **Save invoice** button to save.

**Barcode uniqueness.** `items.barcode` is unique per shop (database index `company_id + barcode` and the
piece form's validation). Another shop may use the same barcode. No migration was needed.

### Manual test with the T-6900

1. **Plug in and check keyboard mode.**
   - Plug the scanner into the counter PC by USB and open **Notepad**.
   - Scan a printed tag (or the test tag). The code should appear as typed text, followed by a new line.
   - **Text and a new line:** the scanner is in keyboard mode with an Enter suffix. This is what the bill page expects.
   - **Text but no new line:** the scanner has no suffix. Scan the "Add CR suffix" or "Enter suffix" setup code from the T-6900 manual. A Tab suffix also works on the bill page.
   - **Nothing at all:** the scanner may be in USB serial (COM) mode. Scan its "USB HID keyboard" setup code from the manual.
   - **Wrong characters** (for example `@` and `"` swapped): set the scanner's keyboard language to match Windows (usually US English).
2. **QR reading.** The tags print a **QR code**, which only a 2D scanner can read. Confirm the T-6900 reads the tag's QR in Notepad. If it is a 1D-only (laser or linear) model, it cannot read these tags.
3. **Bill page.**
   - Open **New bill**. The cursor should already be in Scan tag.
   - Scan a tag. The piece appears under This bill with its price, and the total changes.
   - Scan the same tag again. You should see "already on this bill".
   - Scan a second tag. Both lines appear and the totals add up.
   - Scan an old sold tag. You should see "sold, so it cannot be billed".
   - Type a code by hand and press Enter. It is added the same way.
   - Click into Discount, type, and press Enter. The bill must not save.
   - Choose the customer and payment, then press **Save invoice**. Check the pieces show as Sold.

Record the scanner model and firmware, the suffix setting and the results before calling it tested.

## Troubleshooting

| Message | Meaning |
| --- | --- |
| QZ Tray is not running on this computer | QZ Tray not installed or not started, or the browser blocked the local connection |
| Printer "…" was not found | Name on the tag settings page does not match Windows |
| The print was not allowed | Someone clicked Block in QZ Tray, or signing failed |
| The printer did not accept the tag | Printer off, paused, out of tags, or driver error |
| You have been signed out | Session expired; sign in again |
| The code … has characters a tag cannot hold | The piece's barcode contains `^`, `~` or is too long; edit the piece |
| … is too long for the tag | The piece code with purity does not fit the 20 mm left flap even in the narrowest font; shorten the code |
| The code … is too long for this tag | The barcode needs a QR too big for 13 mm; use a shorter barcode or "Piece code only" |
