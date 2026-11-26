<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Order confirmation</title>
</head>
<body>
    <h1>Thanks for your order</h1>
    <p>Your order <strong>{{ $order->order_number }}</strong> has been received.</p>

    <table>
        <thead>
            <tr>
                <th align="left">Product</th>
                <th align="right">Quantity</th>
                <th align="right">Unit price</th>
                <th align="right">Line total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->product_name }} ({{ $item->product_sku }})</td>
                    <td align="right">{{ $item->quantity }}</td>
                    <td align="right">{{ $item->unit_price }}</td>
                    <td align="right">{{ $item->line_total }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="3" align="right">Order total</th>
                <td align="right">{{ $order->total_amount }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
