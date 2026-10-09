# Health and Medication: How to Use It

This guide is for everyone who records animal health on Chipper Farms. Some steps need a manager; they are marked **(manager)**.

All of it lives under **Monitoring → Health & Medication** in the menu.

## Setting up medicines (manager, once per medicine)

Doses are always recorded against a medicine from the catalogue, so set each medicine up before anyone records a dose.

1. Open **Medicine Catalogue → Add a medicine**.
2. Copy the details from the product label:
   - **Name, formulation and strength**, for example *Oxytetracycline LA*, *Injectable solution*, *200 mg/ml*.
   - **Dose unit**: what a dose is measured in (ml, mg, dose).
   - **Stock unit**: what you count on the shelf (ml, vial, tablet).
   - **Conversion**: how many dose units are in one stock unit. For a liquid counted in ml and dosed in ml, this is 1. For a 50-dose vaccine vial, the dose unit is *dose*, the stock unit is *vial*, and the conversion is 50.
   - **Routes** it can be given by, for example *Injection (IM)*.
   - **Meat withdrawal days**, from the label, and where the figure came from (for example *Product label*). Leave the days blank only if the label gives none. A blank shows as a caution, never as "safe".
   - **Reorder level**, if you want a low-stock reminder.
3. Save, then on the medicine's page use **Receive a batch**. Enter the batch or lot number, the expiry date, the date received, the quantity in stock units, and the cost per stock unit.

After that, the medicine page shows stock on hand per batch, which batches expire soon, recent doses and every adjustment.

- **Adjust** a batch to add, remove or count stock, always with a reason (for example *vial dropped* or *monthly count*). A count sets the stock to what you counted.
- Once a medicine has stock, its units and conversion cannot be changed. For a new pack size, add it as a separate medicine.

## Your day: the Treatment Worklist

Open **Treatment Worklist** each morning. It shows:

- **Overdue from earlier days**: doses that were never recorded.
- **Doses due today**: grouped by medicine and time, each animal with its status.
- **Follow-ups due**: courses and vet re-checks to look at.

When a dose is given, press **Give to N**. The *Record a dose* form opens with the medicine, dose and animals filled in. If an animal was not dosed, remove it from the list before saving, then use **Not given…** on its row to mark it *missed* or *cancelled* with a reason. Marking a dose not given never takes stock.

Tick **Only mine** to see only the courses you are responsible for.

## Recording a dose

Use the worklist, a course page, a case page or **Record a dose** on the Health page.

1. Choose the **animals**. Search by code or by tag colour and number (*green 21*), or add everyone in a room.
2. Enter **when it was given** and **who gave it**. It defaults to you; pick the stockman if you are entering a dose someone else gave.
3. Pick the **medicine**. The form shows its withdrawal period.
4. Enter the amount, either **per animal** or as a **total for the group**, for example medicine put in drinking water.
5. Leave **Take from batch** on *Automatic* to use the batch that expires first, or choose a batch.
6. Check the line that says how much will be taken from stock, then save.

The dose is refused if:

- the amount is in the wrong unit;
- the route is not one the medicine allows;
- the batch has expired; or
- there is not enough stock.

Pressing save twice records it once.

## Health cases

- **Open a case** from the Health page, or from a vet examination. For a problem in several animals, add them all and give the group a name (*Weaner pen 2*). The list is saved as it is now, so later pen moves do not change it.
- **Prescribe a course (manager)** from the case: one or more medicines, each with a dose, route, how often and how many doses. The doses then appear on the worklist.
- **Follow up** as the animal improves or not.
- **Close or resolve** with an outcome when it is over. Finishing a course does not close the case; a person decides that.
- **Reopen** with a reason if the problem comes back.

Vaccination and deworming courses do not need a case. Use **Prescribe a course** and choose *Vaccination* or *Deworming*.

## Vet visits

1. Open **Vet Visits → New visit**. Enter the vet's name, the date and why they came. You can list the animals you plan to show them.
2. For each animal the vet actually examined, add an examination with the result:
   - **No issue found**;
   - **Abnormal findings**: tick *open a case* to start a health case;
   - **Pending**, for example while waiting for test results.
3. Give a follow-up date, or tick *no follow-up needed*.
4. **Finalise (manager)** when the visit is complete. After that, changes need a manager and a reason, and the earlier text is kept.

Planned animals that were not examined are listed separately and never counted as examined.

## Attaching files

Lab results, photos and the vet's written report can be attached to a vet visit, to one animal's examination, to a health case or to a follow-up. Use **Attach** on that page and choose a PDF or photo (up to 10 MB). A manager can remove a wrongly attached file, with a reason; it is kept for the record.

## Withdrawal: can this pig be sold?

Each dose copies the medicine's withdrawal days when it is given. A pig can be sold from the **day after** its last withdrawal ends, and the pig's health page shows that date. Sales, culls for food and the online shop all refuse a pig still under withdrawal.

**Health Reports → Stock, expiry and withdrawal** lists every animal under withdrawal today.

## Fixing mistakes (manager)

Nothing is deleted. On the dose's page:

- **Correct dose** saves the right details. The original stays, marked *corrected*. Its stock goes back to the batch, the corrected amount is taken, and withdrawal is worked out again.
- **Void dose** is for a dose that was never given. The stock goes back and the withdrawal stops applying.

Health records are voided the same way, with a reason. Doses recorded before this upgrade can still be corrected on their old page; they never affect stock.

## Reminders

Farm managers, and whoever is responsible for a course or case, get reminders in the notification bell:

- doses due today (from 6 in the morning);
- doses still not recorded 2 hours after they were due;
- follow-ups due tomorrow;
- pigs whose withdrawal ends within 2 days;
- batches expiring within 30 days;
- medicine at or below its reorder level.

Each reminder is sent once.

## Reports

**Health Reports** has five tabs:

| Tab | Shows |
|---|---|
| Cases and outcomes | Every case with its status and outcome |
| Doses given and missed | Every dose given, plus doses missed or cancelled, with reasons |
| Medicine usage and cost | Grouped by medicine, batch, animal or group (the case or course the dose was for), with a check against the stock records |
| Stock, expiry and withdrawal | Batches in stock, low stock, animals under withdrawal |
| Vet visits | Animals examined (separate from those only planned), results, follow-ups pending |

Each tab has **Download CSV** for staff allowed to export. Reports cover at most one year at a time.
