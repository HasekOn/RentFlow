<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Lease Agreement</title>
    <style>
        @page {
            margin: 25mm 20mm 25mm 20mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            line-height: 1.7;
            color: #1a1a1a;
            margin: 0;
            padding: 0;
        }

        .header {
            text-align: center;
            padding-bottom: 20px;
            margin-bottom: 25px;
            border-bottom: 3px solid #111;
        }

        .header h1 {
            font-size: 24px;
            letter-spacing: 4px;
            color: #111;
            margin: 0 0 4px 0;
            text-transform: uppercase;
        }

        .header .subtitle {
            font-size: 11px;
            color: #666;
            margin: 0;
        }

        .header .logo-text {
            font-size: 9px;
            color: #999;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-top: 6px;
        }

        h2 {
            font-size: 12px;
            color: #111;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin: 28px 0 12px 0;
            padding: 6px 0;
            border-bottom: 1.5px solid #111;
        }

        .parties-table {
            width: 100%;
            margin: 0 0 15px 0;
            border-collapse: collapse;
        }

        .parties-table td {
            width: 48%;
            vertical-align: top;
            padding: 0;
        }

        .parties-table td:first-child {
            padding-right: 12px;
        }

        .parties-table td:last-child {
            padding-left: 12px;
        }

        .party-box {
            border: 1.5px solid #ddd;
            padding: 14px 16px;
        }

        .party-label {
            font-size: 9px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 6px;
        }

        .party-name {
            font-size: 13px;
            font-weight: bold;
            color: #111;
            margin-bottom: 4px;
        }

        .party-detail {
            font-size: 10px;
            color: #555;
            line-height: 1.6;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0 15px 0;
        }

        .info-table td {
            padding: 9px 14px;
            font-size: 11px;
            border: 1px solid #ddd;
            vertical-align: top;
        }

        .info-table .label-cell {
            width: 38%;
            font-weight: bold;
            color: #333;
            background-color: #f7f7f7;
        }

        .info-table .value-cell {
            color: #1a1a1a;
        }

        .info-table .total-row td {
            border-top: 2px solid #111;
            font-weight: bold;
        }

        .article {
            margin: 8px 0 15px 0;
        }

        .article p {
            text-align: justify;
            margin-bottom: 10px;
            font-size: 11px;
            color: #333;
        }

        .signatures-table {
            width: 100%;
            margin-top: 60px;
            border-collapse: collapse;
        }

        .signatures-table td {
            width: 45%;
            text-align: center;
            vertical-align: bottom;
            padding: 0 20px;
        }

        .sig-line {
            border-top: 1px solid #333;
            padding-top: 8px;
            font-size: 10px;
            color: #333;
        }

        .sig-date {
            font-size: 9px;
            color: #888;
            margin-top: 4px;
        }

        .footer {
            margin-top: 50px;
            padding-top: 12px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 9px;
            color: #aaa;
        }
    </style>
</head>
<body>

<div class="header">
    <h1>Lease Agreement</h1>
    <p class="subtitle">Contract No. {{ $lease->id }} / {{ $lease->created_at->format('Y') }}</p>
    <p class="logo-text">RentFlow Property Management</p>
</div>

<h2>I. Contracting Parties</h2>

<table class="parties-table">
    <tr>
        <td>
            <div class="party-box">
                <div class="party-label">Landlord</div>
                <div class="party-name">{{ $landlord->name }}</div>
                <div class="party-detail">{{ $landlord->email }}</div>
                @if($landlord->phone)
                    <div class="party-detail">{{ $landlord->phone }}</div>
                @endif
            </div>
        </td>
        <td>
            <div class="party-box">
                <div class="party-label">Tenant</div>
                <div class="party-name">{{ $tenant->name }}</div>
                <div class="party-detail">{{ $tenant->email }}</div>
                @if($tenant->phone)
                    <div class="party-detail">{{ $tenant->phone }}</div>
                @endif
            </div>
        </td>
    </tr>
</table>

<h2>II. Subject of Lease</h2>

<table class="info-table">
    <tr>
        <td class="label-cell">Address</td>
        <td class="value-cell">{{ $property->address }}</td>
    </tr>
    @if($property->city)
        <tr>
            <td class="label-cell">City</td>
            <td class="value-cell">{{ $property->city }}@if($property->zip_code)
                    , {{ $property->zip_code }}
                @endif</td>
        </tr>
    @endif
    @if($property->disposition)
        <tr>
            <td class="label-cell">Layout</td>
            <td class="value-cell">{{ $property->disposition }}</td>
        </tr>
    @endif
    @if($property->size)
        <tr>
            <td class="label-cell">Area</td>
            <td class="value-cell">{{ number_format($property->size, 2, ',', ' ') }} m²</td>
        </tr>
    @endif
    @if($property->floor)
        <tr>
            <td class="label-cell">Floor</td>
            <td class="value-cell">{{ $property->floor }}{{ $property->floor == 1 ? 'st' : ($property->floor == 2 ? 'nd' : ($property->floor == 3 ? 'rd' : 'th')) }}
                floor
            </td>
        </tr>
    @endif
</table>

<h2>III. Lease Term</h2>

<table class="info-table">
    <tr>
        <td class="label-cell">Commencement Date</td>
        <td class="value-cell">{{ $lease->start_date->format('d. m. Y') }}</td>
    </tr>
    <tr>
        <td class="label-cell">Expiration Date</td>
        <td class="value-cell">{{ $lease->end_date ? $lease->end_date->format('d. m. Y') : 'Indefinite period' }}</td>
    </tr>
    @if($lease->end_date)
        <tr>
            <td class="label-cell">Duration</td>
            <td class="value-cell">{{ $lease->start_date->diffInMonths($lease->end_date) }} months</td>
        </tr>
    @endif
</table>

<h2>IV. Financial Terms</h2>

<table class="info-table">
    <tr>
        <td class="label-cell">Monthly Rent</td>
        <td class="value-cell">{{ number_format($lease->rent_amount, 2, ',', ' ') }} CZK</td>
    </tr>
    @if($lease->utility_advances)
        <tr>
            <td class="label-cell">Utility Advances</td>
            <td class="value-cell">{{ number_format($lease->utility_advances, 2, ',', ' ') }} CZK</td>
        </tr>
        <tr class="total-row">
            <td class="label-cell">Total Monthly Payment</td>
            <td class="value-cell">{{ number_format($lease->rent_amount + $lease->utility_advances, 2, ',', ' ') }}
                CZK
            </td>
        </tr>
    @endif
    @if($lease->deposit_amount)
        <tr>
            <td class="label-cell">Security Deposit</td>
            <td class="value-cell">{{ number_format($lease->deposit_amount, 2, ',', ' ') }} CZK</td>
        </tr>
    @endif
    @if($lease->variable_symbol)
        <tr>
            <td class="label-cell">Variable Symbol</td>
            <td class="value-cell">{{ $lease->variable_symbol }}</td>
        </tr>
    @endif
</table>

<h2>V. Payment Terms</h2>

<div class="article">
    <p>The Tenant agrees to pay the monthly rent and utility advances no later than the <strong>15th day</strong> of
        each calendar month to the Landlord's designated bank account, using the assigned variable symbol for
        identification purposes.</p>
    <p>In the event of a delay in payment exceeding 15 calendar days, the Landlord is entitled to charge a contractual
        penalty of 0.05% of the outstanding amount for each day of delay.</p>
</div>

<h2>VI. Rights and Obligations</h2>

<div class="article">
    <p>The Tenant agrees to use the leased premises in accordance with its intended purpose, to maintain it in good
        condition, and to promptly notify the Landlord of any necessary repairs that fall under the Landlord's
        responsibility.</p>
    <p>The Landlord agrees to ensure the Tenant's undisturbed use of the leased premises for the entire duration of the
        lease and to carry out necessary repairs within a reasonable timeframe.</p>
    <p>Any modifications to the leased premises require prior written consent from the Landlord. The Tenant shall not
        sublease the premises or any part thereof without written consent from the Landlord.</p>
</div>

<h2>VII. Termination of Lease</h2>

<div class="article">
    <p>This lease may be terminated by: (a) written mutual agreement of both parties; (b) expiration of the agreed lease
        term; or (c) written notice of termination by either party with a three-month notice period.</p>
    <p>Upon termination, the Tenant shall vacate and return the premises in a condition corresponding to normal wear and
        tear, together with all keys and access devices.</p>
</div>

<h2>VIII. Final Provisions</h2>

<div class="article">
    <p>This agreement is executed in two counterparts, each party receiving one. The agreement becomes valid and
        effective upon signature by both contracting parties.</p>
    <p>Any matters not governed by this agreement shall be subject to the relevant provisions of the Civil Code of the
        Czech Republic.</p>
</div>

<table class="signatures-table">
    <tr>
        <td>
            <div class="sig-line">{{ $landlord->name }}</div>
            <div class="sig-date">Landlord</div>
        </td>
        <td>
            <div class="sig-line">{{ $tenant->name }}</div>
            <div class="sig-date">Tenant</div>
        </td>
    </tr>
</table>

<div class="footer">
    This document was generated by RentFlow Property Management System on {{ now()->format('d. m. Y') }}
</div>

</body>
</html>
