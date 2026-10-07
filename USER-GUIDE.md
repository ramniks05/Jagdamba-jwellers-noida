# Jagdamba Jewellers — shop user guide

This guide is for the person at the counter. Each section is one job, in the order you do it. On a phone, tap **Menu** to open the list on the left.

Sign in with the shop owner email and password. After you sign in you land on **Overview**. **Log out** is at the bottom of the menu.

**How the amounts are worked out**, at the end of this guide, is the calculation sheet. It shows the sum for a bill, a purchase, old gold, a scheme, and girvi, with the same figures the screen uses.

## Before the shop opens

Do these once, then again only when something changes.

### 1. Shop profile

1. Open **Setup → Shop profile**.
2. Check the shop name, address, phone, GSTIN, and PAN. These print on every bill, girvi receipt, and scheme sheet.
3. Upload the shop logo and the authorised signature. Use a JPEG, PNG, or WebP file, no larger than 2 MB.
4. Save.

The signature shows on the printed invoice. If it is missing, the invoice still prints with a blank line for a handwritten signature.

### 2. Settings

1. Open **Setup → Settings**.
2. Check GST, rounding, and the invoice note at the bottom of the bill.
3. Save.

On this shop, GST is 3% added on top of the piece value, and the bill total is rounded to the nearest rupee.

### 3. Today's metal rate

A bill, a purchase by rate, old gold, and girvi all need a rate for that exact metal and purity.

1. Open **Metal rates**.
2. Choose the metal, for example Gold.
3. Choose the purity, for example 24K or 22K.
4. Type today's rate per gram.
5. Click **Save rate**.

Repeat for every purity you will sell today. If a purity has no rate, the screen cannot price it.

### 4. Supplier, only if you will receive stock

1. Open **Suppliers**.
2. Add the supplier name and mobile.
3. Save.

A purchase cannot be saved until a supplier exists.

## Customers

The customer code is assigned when you save. Do not type a code. The first saved customer after the existing ones is `CUS0003`, then `CUS0004`, and so on.

There is a built-in customer named **Walk-in**. Use Walk-in only for a person who pays in full and will not come back for credit, a scheme, or girvi.

### Add a customer from the menu

1. Open **Customers → Add customer**.
2. Type the name and mobile. Mobile is how you will find them later.
3. Choose the type, usually Retail.
4. Set KYC if you have checked their papers.
5. Click **Save customer**.

### Add a customer while you are billing

On **New bill**, **Take repair**, **Girvi**, and a scheme, click **New customer**.

1. Type the name and mobile.
2. Save. The new customer is selected on that screen. You do not leave the bill.

### Find a customer later

1. Open **Customers**.
2. Search by name, mobile, or code.
3. Open the customer to see bills, the amount they still owe, and to take a later payment with **Save receipt**.

A positive balance means the customer owes the shop. A negative balance means the shop owes the customer, for example after old gold or a matured scheme.

## Sell — one billing screen

All selling is done on **New bill**. **Pieces** is only the stock list. You do not sell from there.

1. Click **New bill**.
2. Search the customer by mobile, name, or code, and choose them. If they are new, click **New customer**.
3. Add the jewellery in one of the two ways below.
4. Check the total on the right. GST is already included in that total.
5. Type the cash, UPI, or card amount. **Put the total in cash** fills the full amount.
6. Click **Save invoice**.
7. Click **Print invoice** and give the A4 sheet to the customer.

A named customer may leave a balance. Walk-in must pay the full total before the bill can be saved.

### Way A — weigh a general piece, such as a ring

Use this when the piece is not a tagged stock item. You choose the product, weigh it, and bill it.

1. In **Weigh and bill**, tap the product, for example Ring, Chain, or Necklace. That fills the name.
2. Choose metal and purity. The rate for that purity fills in if you saved it under **Metal rates**.
3. Type the gross weight in grams.
4. Type making per gram and wastage percent if they apply. Stone weight and stone value are optional.
5. Click **Add to this bill**.
6. Repeat for the next piece on the same bill.

### Way B — sell a tagged piece already in stock

1. In **Tagged piece in stock**, search by name, code, barcode, HUID, or metal.
2. Click the piece. Its saved price is added to the bill.
3. A piece that is already sold will not appear here.

### Return a piece later

1. Open **Sales** and open the invoice.
2. Open **Return a piece later**. This box is not printed on the customer's bill.
3. Tick the piece they brought back.
4. Type a cash refund only if you are handing cash back. Leave it at 0 to keep the value on the customer's account.
5. Click **Save credit note**.

**Refund 0** means no cash left the till. The value sits on the customer's account and can be used on a later bill. Cash refund cannot be more than what that customer has already paid.

### Find an old bill

1. Open **Sales**.
2. Search by invoice number, customer, or mobile.
3. Set **From**, **To**, and **Payment** (All bills, Paid, or Balance due).
4. Click **Filter**.

## Pieces — the stock list

**Pieces** shows what is in the shop. Selling still happens on **New bill**.

1. Open **Pieces**.
2. Use **Add piece** only when you want to tag a piece by hand, with your own item code.
3. Open a piece to see its weight, rate, price, and stock history.
4. From the piece you can reserve it, release a reservation, or mark it damaged or lost.

Stock that arrived through **Receive purchase** also appears here.

## Purchase — receive stock from a supplier

1. Add the supplier first if the list is empty.
2. Open **Purchases → Receive purchase**.
3. Choose the supplier.
4. Type the piece name and an item code. The item code is typed by you. It is not assigned automatically.
5. Choose metal, purity, and location.
6. Type the gross weight.

Choose how to price it:

- **Calculate from today's rate** uses the rate saved under **Metal rates**, plus making and wastage.
- **Enter one purchase value** is the piece value on the supplier's bill, before GST. GST is added on the screen.

The right side shows the piece value, GST, and the total before you save.

7. Payment is optional. **Put the total here** fills the amount you are paying now. Leave payment blank if the supplier balance stays due.
8. Click **Save purchase**. The piece is now in stock and can be sold from **New bill**.

To pay a supplier later, open the supplier and save a payment. To send a piece back, open the purchase and use the return.

## Old gold

The shop buys the customer's old gold. The cut is melting loss, not a making charge. An extra rupee deduction is optional.

1. Open **Old gold → New exchange**.
2. Choose the customer. Walk-in is allowed, but the full cash refund must be paid now.
3. Choose metal and purity. The rate fills from today's rate when the rate box is empty.
4. Type gross weight, stone weight, and melting loss percent.
5. Type a deduction in rupees only if you are cutting an extra amount. Leave it 0 if you are not.
6. Read the panel: net weight, melted weight, gold value, and cash refund.

If the customer already owes the shop, the gold value clears that due first. Cash refund is only what is left.

7. Click **Pay this refund** to copy the cash amount into **Cash given now**. Leave cash at 0 to keep the value on the customer's account.
8. Click **Save exchange**.

Example: 10 g Gold 24K, 2% melting loss, rate ₹12,000 per gram, ₹100 deduction. Melted weight is 9.800 g. Gold value and cash refund are ₹1,17,500 when the customer has no earlier due.

## Repair

The charge is collected when the piece is delivered, not when it is received.

1. Open **Repairs → Take repair**.
2. Search the customer, or add them.
3. Tap the product, such as Ring, or type the piece name.
4. Type what needs repair, the weight received, the estimate, the expected date, and the karigar.
5. Choose a shop piece only if the jewellery is your own stock. Otherwise leave **Customer's own jewellery**.
6. Read the job slip on the right, then click **Save repair**.
7. Print the repair receipt and give it to the customer.

Move the job as the work proceeds:

1. Open the repair.
2. Click **Start inspection**, then **Start repair**, then **Mark ready**.
3. When the customer collects it, type the final charge. It starts from the estimate.
4. Type the payment. A named customer may leave a balance. Walk-in must pay the full charge.
5. Click **Deliver**.

You can cancel a repair before it is marked ready.

## Schemes

A scheme is a monthly saving. The customer pays every month. At the end they get the amount they paid, plus the shop bonus. That amount is credited to their account only when every month is paid, and they can use it on a bill.

Walk-in cannot join a scheme.

### Create the plan

1. Open **Schemes → New scheme**.
2. Type a short code, such as `GOLD11`, and a name.
3. Choose **Fixed monthly amount** when every month is the same figure. Choose **Customer chooses the amount** when each month can differ. A variable scheme does not know the final amount until every month is paid.
4. Type the monthly amount and the number of months.
5. Choose the bonus:
   - **One extra installment from the shop** adds one extra month. This needs a fixed monthly amount.
   - **Percent of the amount paid**.
   - **Fixed bonus amount**.
6. Read **What the customer gets** before you save.
7. Click **Save scheme**.

Example: 11 months × ₹5,000, plus one extra installment. The customer pays ₹55,000. The customer gets ₹60,000.

The scheme page is a sheet you can hand over before they join. Click **Print scheme**, or **Send to customer** to open WhatsApp with the same figures.

### Enroll and collect

1. Open the scheme.
2. Search the customer by mobile or name. Click **Enroll**.
3. Open the passbook.
4. Each month, check the amount and click **Save installment**.
5. When every month is paid, click **Mature scheme**. The closing amount is credited to the customer.

**Print passbook** is the copy for the customer. **Send to customer** opens WhatsApp on that customer's mobile.

## Girvi

The shop keeps the customer's gold and gives cash now. Interest is a simple percent of the loan for each month. A part of a month is charged as one full month. The same day counts as one month.

Walk-in cannot be used for girvi. The loan cannot be more than the gold value.

One girvi can hold several pieces. Each piece is priced on its own metal, karat, weight, and rate. Add the grams only when every piece is the same metal and the same karat. The loan percent and the interest apply once, to the total rupee value.

1. Open **Girvi → New girvi**.
2. Search the customer, or add them.
3. Tap the piece, such as Chain. Choose metal and purity. Type the weight. The rate fills from today's rate.
4. Click **Add this piece**.
5. Repeat for the next piece. Remove a piece if it was added by mistake.
6. Leave **Percent of the gold value** selected, usually 75. Or choose **Enter one loan amount** and type the rupees.
7. Type the interest percent per month, usually 2.
8. Read the panel: each piece, gold value, loan given now, interest for 1 month, and the amount to release after 1 month.
9. Click **Save girvi**. Cash for the loan is given now.
10. Click **Print receipt** or **Send to customer**.

Example: Chain 10 g and Ring 5 g, Gold 24K at ₹12,000 per gram, 75% loan, 2% interest.

| Piece | Weight | Gold value |
| --- | --- | --- |
| Chain | 10 g | ₹1,20,000 |
| Ring | 5 g | ₹60,000 |
| Total | 15 g | ₹1,80,000 |

Loan given now is ₹1,35,000. Interest for one month is ₹2,700. To release the gold after one month the customer pays ₹1,37,700. The 15 g line is valid here because both pieces are Gold 24K. A 22K piece and a silver piece stay on their own lines.

### Interest only, or release

1. Open the girvi.
2. Check **Months to charge**. It is counted from the last interest date.
3. Click **Take interest only** to keep the gold and collect interest. The loan does not reduce.
4. Click **Release the gold** when the customer pays the loan plus the interest. The amount must match the figure on the screen.

The receipt lists every piece, the loan in words, the cash given, the interest, and today's release amount. The customer signs it, and the shop signs it.

## Reports

Each report has **Print report**.

- **Reports → Stock** — pieces still in the shop, and their weight. A sold piece does not appear.
- **Reports → Sales** — bills for the period. It opens on this month. Change the dates and filter.
- **Reports → Outstanding** — customers who still owe the shop, and supplier balances.

## Masters and setup

Use these when the lists need a new choice. You do not open them for a normal bill.

| Menu | Use it for |
| --- | --- |
| Categories | Product names on the bill, such as Ring or Necklace |
| Brands | Brand names |
| Collections | A named collection |
| Designs | A named design |
| Metals | Gold, silver, and their purities |
| Stones | Stone types and grades |
| Making and wastage | The charge methods used on a bill |
| Branches | Another branch, if you have one |
| Financial years | The April–March year. Close a year only when that year is finished |
| Document numbers | The prefixes, such as INV, PUR, OG, REP, SCH, and GRV |
| Users | A cashier or another staff login |
| Roles | What each login is allowed to do |
| Profile | Your own name and password |

A cashier can make a bill and add a customer. A cashier cannot add a stock piece from the Pieces menu. Pieces weighed on the bill can still be added by the cashier.

## How the amounts are worked out

Every money figure on a screen comes from the rules below. Weights are in grams, to 3 decimals. Money is in rupees, to 2 decimals (paise). Half a paisa and above rounds up. When the bill is set to round to the nearest rupee, 50 paise and above rounds up to the next rupee, and less than 50 paise rounds down.

On this shop, GST is 3% added on top, and the bill total is rounded to the nearest rupee. Those two settings are under **Setup → Settings**.

### Weight on a piece

```text
Net grams = Gross grams − Stone grams
```

Stone weight cannot be more than the gross weight. The metal value always uses net grams, not the gross weight.

### A bill

Each piece is worked out, then the bill adds GST once.

```text
Metal value   = Net grams × Rate per gram
Wastage       = see the three methods below
Making        = see the three methods below
Stone value   = the rupee amount you type
Piece value   = Metal + Wastage + Making + Stone
```

Wastage, pick one:

| Method | Sum |
| --- | --- |
| Percent | Net grams × percent ÷ 100 × rate per gram |
| Per gram | Net grams × rupees per gram |
| Fixed amount | The rupees you type |

Making, pick one:

| Method | Sum |
| --- | --- |
| Per gram | Net grams × rupees per gram |
| Percent | Metal value × percent ÷ 100 |
| Fixed amount, or per piece | The rupees you type |

Wastage per gram and making per gram are rupees. They are not a percent of the gold rate.

Then the bill:

```text
Item total    = all piece values added together
After discount = Item total − discount in rupees
GST           = After discount × 3 ÷ 100
Exact total   = After discount + GST
Bill total    = Exact total rounded to the nearest rupee
```

The discount cannot be more than the item total. The round-off line on the invoice is the difference between the exact total and the bill total. It can be a few paise added or a few paise taken off.

Worked bill. One ring, Gold 22K, rate ₹10,000 per gram. Gross 10.000 g, stone 0.500 g, wastage 8%, making ₹400 per gram, stone value ₹1,500, no discount.

| Step | Figure |
| --- | --- |
| Net weight | 10.000 − 0.500 = 9.500 g |
| Metal | 9.500 × 10,000 = ₹95,000.00 |
| Wastage 8% | 9.500 × 8 ÷ 100 × 10,000 = ₹7,600.00 |
| Making | 9.500 × 400 = ₹3,800.00 |
| Stone | ₹1,500.00 |
| Piece value | ₹1,07,900.00 |
| GST 3% | ₹3,237.00 |
| Bill total | ₹1,11,137.00 |

A second piece on the same bill is added into the item total before GST. GST is not calculated twice.

If the exact total is ₹10,310.30, the bill total becomes ₹10,310. The 30 paise is the round off.

A tagged piece uses the weight, rate, making, and wastage already saved on that piece. The same sums apply.

What the customer still owes on that bill is the bill total minus the cash, UPI, and card taken now. A named customer may leave that balance. Walk-in must pay the bill total in full.

### A return

The credit is that piece's share of the full bill, including its share of GST and round off. Return every piece and the credit equals the bill total.

**Refund 0** leaves that credit on the customer's account for a later bill. A cash refund cannot be more than the credit on the account. Walk-in must be refunded in full.

### A purchase

Net weight is the same: gross minus stone.

**Calculate from today's rate** uses the same piece sum as a bill: metal, wastage, making, and stone, from the rate saved for that metal and purity. GST is then added the same way as a bill.

**Enter one purchase value** is the piece value before GST. Today's rate is not used.

```text
GST         = Purchase value × 3 ÷ 100
Total       = Purchase value + GST, then rounded to the nearest rupee
Stored rate = Purchase value ÷ Net grams
```

Worked purchase. Net 10.000 g, purchase value typed as ₹80,000.

| Step | Figure |
| --- | --- |
| GST 3% | ₹2,400.00 |
| Total to the supplier | ₹82,400.00 |
| Rate stored on the piece | 80,000 ÷ 10 = ₹8,000.00 per gram |

Pay now, or leave the supplier balance due. A later payment reduces what the shop owes that supplier.

### Old gold

The cut is melting loss. There is no making charge.

```text
Net grams      = Gross grams − Stone grams
Melted grams   = Net grams × (100 − melting loss percent) ÷ 100
Metal value    = Melted grams × Rate per gram
Exchange value = Metal value − extra deduction in rupees
```

Melting loss must be from 0 to 100 percent. The extra deduction cannot be more than the metal value. Leave it at 0 when you are not cutting anything else.

Worked exchange. 10.000 g Gold 24K, no stone, 2% melting loss, rate ₹12,000 per gram, ₹100 deduction, and the customer does not already owe the shop.

| Step | Figure |
| --- | --- |
| Net weight | 10.000 g |
| Melted weight | 10.000 × 98 ÷ 100 = 9.800 g |
| Metal value | 9.800 × 12,000 = ₹1,17,600.00 |
| Exchange value | ₹1,17,600.00 − ₹100.00 = ₹1,17,500.00 |

The exchange value is credited to the customer first. If they already owe the shop, that due is cleared before any cash is given. Cash refund is only what is left. Leave the cash at 0 to keep the rest on their account. Walk-in must take the full cash refund now.

Example with an earlier due of ₹20,000: exchange value ₹1,17,500 clears the ₹20,000, and the cash that can be given is ₹97,500.

### A repair

There is no weight sum. The estimate is the figure you type when the piece comes in. The charge is the figure you type at delivery. It starts from the estimate, and you can change it.

A named customer may pay part now and leave a balance. Walk-in must pay the full charge before delivery.

### A scheme

The customer gets the amount they paid, plus the shop bonus. The bonus is credited only when every month is paid and you click **Mature scheme**.

```text
Amount paid = every installment added together
```

Bonus, pick one:

| Bonus | What is added |
| --- | --- |
| One extra installment | One more month of the fixed monthly amount. This needs a fixed monthly amount. |
| Percent of the amount paid | Amount paid × percent ÷ 100 |
| Fixed bonus amount | The rupees you typed on the scheme |

```text
Customer gets = Amount paid + Bonus
```

Worked scheme. 11 months × ₹5,000. The customer pays ₹55,000.

| Bonus chosen | Customer gets |
| --- | --- |
| One extra installment | ₹55,000 + ₹5,000 = ₹60,000 |
| 2 percent | ₹55,000 + ₹1,100 = ₹56,100 |
| Fixed bonus ₹5,000 | ₹55,000 + ₹5,000 = ₹60,000 |

A scheme where the customer chooses each month's amount does not know the final figure until every month is paid. It cannot use the extra-installment bonus.

The closing amount is credited to the customer. They spend it on a later bill. It is not handed out as gold.

### Girvi

Each piece:

```text
Net grams   = Gross grams − Stone grams
Piece value = Net grams × Rate per gram for that piece's metal and karat
Total value = every piece value added together
```

Do not add grams of different metals or different karats into one weight. Gold 22K, Gold 24K, and silver each keep their own grams. The loan uses the rupee total only.

Loan, pick one:

```text
Percent loan = Total value × loan percent ÷ 100
Typed loan   = the rupees you type
```

The loan must be more than zero and cannot be more than the total value.

```text
Interest for the months = Loan × interest percent per month × months ÷ 100
To release             = Loan + Interest
```

A part of a month counts as one full month. The same day counts as one month. After that, every 30 days is another month, and any days left over start the next month.

| Days the gold has stayed | Months charged |
| --- | --- |
| Same day | 1 |
| 1 to 30 days | 1 |
| 31 to 60 days | 2 |
| 61 to 90 days | 3 |

Worked girvi. Both pieces are Gold 24K at ₹12,000 per gram. Loan 75%. Interest 2% per month.

| Piece | Net weight | Value |
| --- | --- | --- |
| Chain | 10.000 g | 10 × 12,000 = ₹1,20,000.00 |
| Ring | 5.000 g | 5 × 12,000 = ₹60,000.00 |
| Total | 15.000 g of Gold 24K | ₹1,80,000.00 |

| Step | Figure |
| --- | --- |
| Loan given now | 1,80,000 × 75 ÷ 100 = ₹1,35,000.00 |
| Interest for 1 month | 1,35,000 × 2 ÷ 100 = ₹2,700.00 |
| To release after 1 month | ₹1,35,000 + ₹2,700 = ₹1,37,700.00 |
| Interest for 2 months | 1,35,000 × 2 × 2 ÷ 100 = ₹5,400.00 |
| To release after 2 months | ₹1,35,000 + ₹5,400 = ₹1,40,400.00 |

**Take interest only** collects the interest and leaves the loan as it is. The gold stays in the shop. The next interest count starts from that day.

**Release the gold** collects the loan plus the interest. The amount must match the figure on the screen. The gold is handed back and the loan is cleared.

### The customer's balance

```text
Balance = what was charged to the customer − what was credited or paid
```

A positive balance means the customer owes the shop. A negative balance means the shop owes the customer.

| What happened | Effect on the balance |
| --- | --- |
| A bill is saved | The bill total is added to what they owe |
| They pay cash, UPI, or card | That payment reduces what they owe |
| Old gold is saved | The exchange value reduces what they owe, or creates credit |
| Cash is given for old gold | That cash reduces the credit |
| A return is saved | The credit-note amount reduces what they owe |
| Girvi cash is given | The loan is added to what they owe |
| Girvi interest only | The interest is paid, and the loan stays |
| Girvi is released | The loan and the interest are paid, and the loan is cleared |
| A scheme is matured | The closing amount becomes credit they can use on a bill |

### What the home page is adding up

| Tile | Sum |
| --- | --- |
| Today's sales | Bill totals dated today |
| Cash out today | Supplier payments today, plus old-gold cash refunds today. A girvi loan given today is included too |
| To collect | Customers whose balance is still positive |
| Repairs in shop | Jobs that are not delivered and not cancelled |
| Open girvi | Loans on pledges whose gold is still in the shop |
| Schemes | Members whose scheme is still running, and installments due on or before today |

## Everyday order

1. Open **Metal rates** and save today's rates.
2. Click **New bill** for every sale.
3. Use **Purchases** when stock arrives.
4. Use **Old gold**, **Repairs**, **Schemes**, and **Girvi** only for that job.
5. At the end of the day, open **Reports → Sales** and **Outstanding**.

## If something will not price

- Save the rate for that exact metal and purity under **Metal rates**.
- Choose a customer before saving a bill. Walk-in must be paid in full.
- A purchase needs a supplier and an item code.
- Girvi and schemes cannot use Walk-in.
- A sold piece will not show in **New bill** search or in the stock report.
