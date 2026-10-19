<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>{{ $number }}</title>
<style>
    @page { margin: 36px 40px; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 10.5px; color: #1f2933; }
    table { width: 100%; border-collapse: collapse; }
    td, th { vertical-align: top; }
    .supplier { font-size: 17px; font-weight: bold; color: #0b3d5c; }
    .muted { color: #52606d; }
    .title { font-size: 22px; font-weight: bold; letter-spacing: 3px; text-align: right; color: #0b3d5c; }
    .copy { text-align: right; color: #b42318; font-weight: bold; letter-spacing: 1px; }
    .rule { border-top: 2px solid #0b3d5c; margin: 14px 0 12px; }
    .meta td { padding: 2px 0; }
    .meta .label { width: 92px; color: #52606d; }
    .items { margin-top: 18px; }
    .items th { background: #0b3d5c; color: #fff; padding: 6px 6px; text-align: left; font-size: 10px; }
    .items td { padding: 6px; border-bottom: 1px solid #d9e2ec; }
    .num { text-align: right; white-space: nowrap; }
    .totals { width: 46%; margin-left: 54%; margin-top: 10px; }
    .totals td { padding: 4px 6px; }
    .totals .grand td { border-top: 2px solid #0b3d5c; font-weight: bold; font-size: 12px; padding-top: 7px; }
    .footer { margin-top: 28px; }
    .sign { text-align: center; width: 200px; }
</style>
</head>
<body>
<table>
    <tr>
        <td>
            <div class="supplier">{{ $supplier }}</div>
            @foreach ($address as $line)
                <div class="muted">{{ $line }}</div>
            @endforeach
            <div class="muted">Telp. {{ $phone }}</div>
            <div class="muted">NPWP: {{ $npwp }}</div>
        </td>
        <td>
            <div class="title">INVOICE</div>
            @if ($copy ?? false)
                <div class="copy">SALINAN / COPY</div>
            @endif
        </td>
    </tr>
</table>

<div class="rule"></div>

<table>
    <tr>
        <td style="width: 55%">
            <div class="muted">Kepada Yth.</div>
            <div><strong>{{ $customer[0] }}</strong></div>
            <div>{{ $customer[1] }}</div>
        </td>
        <td>
            <table class="meta">
                <tr><td class="label">No. Invoice</td><td>: {{ $number }}</td></tr>
                <tr><td class="label">Tanggal</td><td>: {{ $date->translatedFormat('j F Y') }}</td></tr>
                <tr><td class="label">Jatuh Tempo</td><td>: {{ $due->translatedFormat('j F Y') }}</td></tr>
                @if ($po)
                    <tr><td class="label">No. PO</td><td>: {{ $po }}</td></tr>
                @endif
            </table>
        </td>
    </tr>
</table>

<table class="items">
    <thead>
        <tr>
            <th style="width: 24px">No</th>
            <th>Deskripsi</th>
            <th class="num">Qty</th>
            <th>Satuan</th>
            <th class="num">Harga Satuan</th>
            <th class="num">Jumlah</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($items as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item['name'] }}</td>
                <td class="num">{{ number_format($item['qty'], 0, ',', '.') }}</td>
                <td>{{ $item['unit'] }}</td>
                <td class="num">{{ rupiah($item['price']) }}</td>
                <td class="num">{{ rupiah($item['amount']) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totals">
    <tr><td>Subtotal</td><td class="num">{{ rupiah($subtotal) }}</td></tr>
    @if ($taxBase)
        <tr><td>DPP Nilai Lain (11/12)</td><td class="num">{{ rupiah($taxBase) }}</td></tr>
    @endif
    <tr><td>PPN {{ $tax_rate }}%</td><td class="num">{{ rupiah($tax) }}</td></tr>
    <tr class="grand"><td>TOTAL</td><td class="num">{{ rupiah($total) }}</td></tr>
</table>

<table class="footer">
    <tr>
        <td>
            <div class="muted">Pembayaran ditransfer ke:</div>
            <div>{{ $bank }}</div>
            <div class="muted" style="margin-top: 8px">Mohon cantumkan nomor invoice pada berita transfer.</div>
        </td>
        <td class="sign">
            <div class="muted">Hormat kami,</div>
            <div style="height: 54px"></div>
            <div>( Bagian Keuangan )</div>
        </td>
    </tr>
</table>
</body>
</html>
