<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            line-height: 1.6;
            color: #333;
            padding: 40px;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #2c3e50;
            padding-bottom: 15px;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 22px;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .header p {
            font-size: 11px;
            color: #7f8c8d;
        }

        h2 {
            font-size: 14px;
            color: #2c3e50;
            margin-top: 25px;
            margin-bottom: 10px;
            border-bottom: 1px solid #bdc3c7;
            padding-bottom: 5px;
        }

        .parties {
            width: 100%;
            margin-bottom: 20px;
        }

        .parties td {
            width: 50%;
            vertical-align: top;
            padding: 10px;
        }

        .party-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
        }

        .party-box .label {
            font-size: 10px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .party-box .name {
            font-size: 14px;
            font-weight: bold;
            color: #2c3e50;
        }

        .party-box .detail {
            font-size: 11px;
            color: #555;
            margin-top: 3px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        .info-table td {
            padding: 8px 12px;
            border: 1px solid #dee2e6;
        }

        .info-table .label-cell {
            background: #f8f9fa;
            font-weight: bold;
            width: 40%;
            color: #2c3e50;
        }

        .article {
            margin-bottom: 15px;
        }

        .article p {
            text-align: justify;
            margin-bottom: 8px;
        }

        .signatures {
            margin-top: 60px;
            width: 100%;
        }

        .signatures td {
            width: 50%;
            text-align: center;
            padding-top: 40px;
        }

        .signature-line {
            border-top: 1px solid #333;
            width: 200px;
            margin: 0 auto;
            padding-top: 5px;
            font-size: 11px;
        }

        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 10px;
            color: #95a5a6;
            border-top: 1px solid #dee2e6;
            padding-top: 10px;
        }
    </style>
</head>
<body>

<div class="header">
    <h1>LEASE AGREEMENT</h1>
    <p>No. {{ $lease->id }} / {{ $lease->created_at->format('Y') }}</p>
</div>

<h2>I. Contracting Parties</h2>

<table class="parties">
    <tr>
        <td>
            <div class="party-box">
                <div class="label">Landlord</div>
                <div class="name">{{ $landlord->name }}</div>
                <div class="detail">{{ $landlord->email }}</div>
                @if($landlord->phone)
                    <div class="detail">Phone: {{ $landlord->phone }}</div>
                @endif
            </div>
        </td>
        <td>
            <div class="party-box">
                <div class="label">Tenant</div>
                <div class="name">{{ $tenant->name }}</div>
                <div class="detail">{{ $tenant->email }}</div>
                @if($tenant->phone)
                    <div class="detail">Phone: {{ $tenant->phone }}</div>
                @endif
            </div>
        </td>
    </tr>
</table>

<h2>II. Subject of Lease</h2>

<table class="info-table">
    <tr>
        <td class="label-cell">Address</td>
        <td>{{ $property->address }}</td>
    </tr>
    @if($property->city)
        <tr>
            <td class="label-cell">City</td>
            <td>{{ $property->city }} {{ $property->zip_code }}</td>
        </tr>
    @endif
    <tr>
        <td class="label-cell">Layout</td>
        <td>{{ $property->disposition }}</td>
    </tr>
    <tr>
        <td class="label-cell">Area</td>
        <td>{{ $property->size }} m²</td>
    </tr>
    @if($property->floor)
        <tr>
            <td class="label-cell">Floor</td>
            <td>{{ $property->floor }}.</td>
        </tr>
    @endif
</table>

<h2>III. Lease Term</h2>

<table class="info-table">
    <tr>
        <td class="label-cell">Lease Start</td>
        <td>{{ $lease->start_date->format('d.m.Y') }}</td>
    </tr>
    <tr>
        <td class="label-cell">Lease End</td>
        <td>{{ $lease->end_date ? $lease->end_date->format('d.m.Y') : 'Indefinite period' }}</td>
    </tr>
</table>

<h2>IV. Financial Terms</h2>

<table class="info-table">
    <tr>
        <td class="label-cell">Monthly Rent</td>
        <td>{{ number_format($lease->rent_amount, 2, '.', ' ') }} CZK</td>
    </tr>
    @if($lease->utility_advances)
        <tr>
            <td class="label-cell">Utility Advances</td>
            <td>{{ number_format($lease->utility_advances, 2, '.', ' ') }} CZK</td>
        </tr>
        <tr>
            <td class="label-cell">Total Monthly</td>
            <td><strong>{{ number_format($lease->rent_amount + $lease->utility_advances, 2, '.', ' ') }} CZK</strong>
            </td>
        </tr>
    @endif
    @if($lease->deposit_amount)
        <tr>
            <td class="label-cell">Security Deposit</td>
            <td>{{ number_format($lease->deposit_amount, 2, '.', ' ') }} CZK</td>
        </tr>
    @endif
    @if($lease->variable_symbol)
        <tr>
            <td class="label-cell">Variable Symbol</td>
            <td>{{ $lease->variable_symbol }}</td>
        </tr>
    @endif
</table>

<h2>V. Payment Terms</h2>

<div class="article">
    <p>The Tenant agrees to pay the rent and utility advances no later than the 15th day of the respective calendar
        month to the Landlord's bank account under the assigned variable symbol.</p>
    <p>In the event of a delay in payment exceeding 15 days, the Landlord is entitled to charge a contractual penalty of
        0.05% of the outstanding amount for each day of delay.</p>
</div>

<h2>VI. Rights and Obligations</h2>

<div class="article">
    <p>The Tenant agrees to use the leased property properly, in accordance with its purpose, and to maintain it in good
        condition. The Tenant is obliged to notify the Landlord without undue delay of the need for repairs to be borne
        by the Landlord.</p>
    <p>The Landlord agrees to ensure the Tenant's undisturbed use of the leased property for the entire duration of the
        lease and to carry out necessary repairs within a reasonable time.</p>
</div>

<h2>VII. Termination of Lease</h2>

<div class="article">
    <p>The lease may be terminated by a written agreement of both parties, by the expiration of the agreed period, or by
        a notice of termination with a three-month notice period. Upon termination of the lease, the Tenant is obliged
        to hand over the apartment in a condition corresponding to normal wear and tear.</p>
</div>

<h2>VIII. Final Provisions</h2>

<div class="article">
    <p>This agreement is drawn up in two counterparts, of which each party shall receive one. The agreement becomes
        valid and effective on the date of signature by both contracting parties.</p>
    <p>Relations not regulated by this agreement are governed by the relevant provisions of the Civil Code.</p>
</div>

<table class="signatures">
    <tr>
        <td>
            <div class="signature-line">Landlord — {{ $landlord->name }}</div>
        </td>
        <td>
            <div class="signature-line">Tenant — {{ $tenant->name }}</div>
        </td>
    </tr>
</table>

<div class="footer">
    Generated by the RentFlow system on {{ now()->format('d.m.Y') }}
</div>

</body>
</html>
