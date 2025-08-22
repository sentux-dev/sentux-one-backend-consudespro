<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Cotización {{ $quote->quote_number }}</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10px;
            color: #333;
        }
        .container {
            width: 100%;
            margin: 0;
        }
        .header-table {
            width: 100%;
            margin-bottom: 25px;
        }
        .header-table td {
            vertical-align: top;
        }
        .logo {
            max-width: 160px;
            max-height: 80px;
        }
        .quote-title {
            text-align: right;
            font-size: 28px;
            color: #333;
        }
        .quote-number {
            text-align: right;
            font-size: 12px;
            color: #777;
        }
        .info-table {
            width: 100%;
            margin-bottom: 25px;
        }
        .info-table td {
            width: 50%;
            vertical-align: top;
        }
        .info-table h3 {
            font-size: 10px;
            margin-bottom: 4px;
            color: #888;
            text-transform: uppercase;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        .items-table thead th {
            background-color: #f2f2f2;
            text-align: left;
            padding: 10px 8px;
            border-bottom: 2px solid #333;
        }
        .items-table tbody td {
            padding: 10px 8px;
            border-bottom: 1px solid #ddd;
        }
        .items-table .text-right {
            text-align: right;
        }
        .summary-section {
            width: 100%;
            margin-top: 20px;
        }
        .totals-table {
            width: 40%;
            float: right;
            font-size: 11px;
        }
        .totals-table td {
            padding: 5px 8px;
        }
        .totals-table .grand-total {
            font-size: 14px;
            font-weight: bold;
            border-top: 2px solid #333;
            border-bottom: 2px solid #333;
        }
        .payment-info-section {
            clear: both;
            padding-top: 30px;
        }
        .payment-info-section h4, .notes-section h4 {
            font-size: 12px;
            margin-bottom: 5px;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }
        .footer {
            position: fixed;
            bottom: -30px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 9px;
            color: #888;
        }
        .whitespace-pre-line {
            white-space: pre-line;
        }
    </style>
</head>
<body>
    <div class="container">
        <table class="header-table">
            <tr>
                <td>
                    @if($quote->issuing_company->logo_path)
                        <img src="{{ public_path('storage/' . $quote->issuing_company->logo_path) }}" alt="Logo" class="logo">
                    @else
                        <h2 style="font-size: 18px;">{{ $quote->issuing_company->name }}</h2>
                    @endif
                </td>
                <td style="text-align: right;">
                    <div class="quote-title">COTIZACIÓN</div>
                    <div class="quote-number">#{{ $quote->quote_number }}</div>
                </td>
            </tr>
        </table>

        <table class="info-table">
            <tr>
                <td>
                    <h3>De</h3>
                    <strong>{{ $quote->issuing_company->name }}</strong><br>
                    ID: {{ $quote->issuing_company->tax_id }}<br>
                    {{ $quote->issuing_company->address }}<br>
                    {{ $quote->issuing_company->email }}
                </td>
                <td>
                    <h3>Para</h3>
                    <strong>{{ $quote->contact->first_name }} {{ $quote->contact->last_name }}</strong><br>
                    {{ $quote->contact->email }}<br>
                    {{ $quote->contact->cellphone }}
                </td>
            </tr>
            <tr>
                <td style="padding-top: 15px;">
                    <h3>Fecha de Emisión</h3>
                    {{ $quote->created_at->format('d/m/Y') }}
                </td>
                <td style="padding-top: 15px;">
                    <h3>Válida Hasta</h3>
                    {{ $quote->valid_until ? $quote->valid_until->format('d/m/Y') : 'N/A' }}
                </td>
            </tr>
        </table>

        <table class="items-table">
            <thead>
                <tr>
                    <th>Descripción</th>
                    <th class="text-right">Cantidad</th>
                    <th class="text-right">Precio Unit.</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($quote->items as $item)
                <tr>
                    <td>
                        <strong>{{ $item->description }}</strong>
                    </td>
                    <td class="text-right">{{ number_format($item->quantity, 2) }}</td>
                    <td class="text-right">${{ number_format($item->unit_price, 2) }}</td>
                    <td class="text-right">${{ number_format($item->line_total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <table class="summary-section">
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    @if(!empty($quote->issuing_company->bank_accounts))
                    <div class="payment-info-section">
                        <h4>Información de Pago</h4>
                        @foreach($quote->issuing_company->bank_accounts as $account)
                            <p style="margin-bottom: 8px;">
                                <strong>Banco:</strong> {{ $account['bank_name'] }}<br>
                                <strong>Cuenta ({{ $account['currency'] }}):</strong> {{ $account['account_number'] }}
                            </p>
                        @endforeach
                    </div>
                    @endif
                </td>
                <td style="width: 50%; vertical-align: top;">
                    <table class="totals-table">
                        <tr>
                            <td>Subtotal</td>
                            <td class="text-right">${{ number_format($quote->subtotal, 2) }}</td>
                        </tr>
                        @foreach($quote->tax_details ?? [] as $tax)
                        <tr>
                            <td>{{ $tax['name'] }} ({{ $tax['rate'] }}%)</td>
                            <td class="text-right">${{ number_format($tax['amount'], 2) }}</td>
                        </tr>
                        @endforeach
                        <tr class="grand-total">
                            <td>TOTAL</td>
                            <td class="text-right">${{ number_format($quote->grand_total, 2) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        
        @if($quote->notes_customer || $quote->issuing_company->default_notes)
        <div class="notes-section">
            <h4>Notas:</h4>
            <p class="whitespace-pre-line">
                {{ $quote->notes_customer ?: $quote->issuing_company->default_notes }}
            </p>
        </div>
        @endif

        @if($quote->issuing_company->pdf_footer_text)
            <div class="footer">
                {{ $quote->issuing_company->pdf_footer_text }}
            </div>
        @endif
    </div>
</body>
</html>