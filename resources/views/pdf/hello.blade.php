<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; }
        h1 { font-size: 22px; margin-bottom: 4px; }
        .muted { color: #6b7280; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; font-size: 13px; }
        th { background: #f3f4f6; }
        .amount { text-align: right; }
    </style>
</head>
<body>
    <h1>Hello from HRM &amp; Payroll</h1>
    <p class="muted">Generated for {{ $name }} on {{ $generatedAt }}</p>

    <table>
        <tr><th>Head</th><th class="amount">Amount</th></tr>
        <tr><td>Basic</td><td class="amount">30,000.00</td></tr>
        <tr><td>House Rent</td><td class="amount">15,000.00</td></tr>
        <tr><th>Gross</th><th class="amount">45,000.00</th></tr>
    </table>
</body>
</html>
