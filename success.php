<?php
// Include configuration file
include 'config.php';

$is_paypal_payment = (isset($_GET['tx']) && isset($_GET['st']) && $_GET['st'] === 'Completed');
$is_stripe_embedded = (isset($_GET['session_id']) && !empty($_GET['session_id']));
$is_gpay_payment = (isset($_GET['payment_method']) && $_GET['payment_method'] === 'gpay');
$is_square_payment = (isset($_GET['payment_method']) && $_GET['payment_method'] === 'square');

$txn_id = '';
$payment_status = '';
$cart_items = [];
$grand_total = 0;
$customer_email = '';
$is_successful_payment = false;

// Handle PayPal transaction data from URL
if ($is_paypal_payment) {
    $is_successful_payment = true;
    $txn_id = $_GET['tx'];
    $payment_status = $_GET['st'];
    $customer_email = isset($_GET['payer_email']) ? $_GET['payer_email'] : 'customer@paypal.com';
    
    $num_items = !empty($_GET['num_cart_items']) ? intval($_GET['num_cart_items']) : 0;
    
    for ($i = 1; $i <= $num_items; $i++) {
        if (!empty($_GET["item_name{$i}"]) && !empty($_GET["quantity{$i}"]) && !empty($_GET["mc_gross_{$i}"])) {
            $item_name = $_GET["item_name{$i}"];
            $quantity = intval($_GET["quantity{$i}"]);
            $price_per_item = floatval($_GET["mc_gross_{$i}"]);
            
            $cart_items[] = [
                'name' => $item_name,
                'quantity' => $quantity,
                'total_price' => $price_per_item
            ];
            $grand_total += $price_per_item;
        }
    }
} else if ($is_gpay_payment || $is_stripe_embedded || $is_square_payment) {
    // For these payment methods, success is handled by the presence of a URL parameter
    // and the details are populated by client-side JavaScript.
    $is_successful_payment = true;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Gateway</title>
    <link href="assets/css/bootstrap.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet" />
    <script src="https://js.stripe.com/v3/"></script>
</head>
<body>

<div class="container" style="margin-top: 50px;">
    <div class="status text-center">
        <?php if ($is_successful_payment) { ?>
            <div id="unified-success-page">
                <h1 class="success">Your Payment has been Successful!</h1>
                <div id="payment-summary-container">
                    </div>
            </div>
        <?php } else { ?>
            <h1 class="error">Your Payment has Failed!</h1>
            <p>Please check your payment details or try again later.</p>
        <?php } ?>
    </div>
    <div class="text-center" style="margin-top: 40px;">
        <a href="index.html" class="btn btn-primary">Back to Products</a>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const isPayPal = <?php echo json_encode($is_paypal_payment); ?>;
        const isStripe = <?php echo json_encode($is_stripe_embedded); ?>;
        const isGooglePay = <?php echo json_encode($is_gpay_payment); ?>;
        const isSquare = <?php echo json_encode($is_square_payment); ?>;

        // Function to create and populate the order summary table
        function populateSummary(transactionId, paymentStatus, customerEmail, cartItems) {
            let grandTotal = 0;
            let tableRows = '';

            for (const id in cartItems) {
                const item = cartItems[id];
                const itemTotal = item.price * item.qty;
                grandTotal += itemTotal;
                tableRows += `
                    <tr>
                        <td>${item.name}</td>
                        <td>${item.qty}</td>
                        <td>$${itemTotal.toFixed(2)}</td>
                    </tr>
                `;
            }

            const summaryHtml = `
                <h4>Payment Summary</h4>
                <p><b>Transaction ID:</b> ${transactionId}</p>
                <p><b>Payment Status:</b> ${paymentStatus}</p>
                
                <h4 style="margin-top: 30px;">Order Details</h4>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Quantity</th>
                            <th>Total Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${tableRows}
                    </tbody>
                </table>
                <h4 style="margin-top: 30px;">Grand Total: $${grandTotal.toFixed(2)}</h4>
                <p style="margin-top: 20px;">An email confirmation has been sent to ${customerEmail}.</p>
            `;
            return summaryHtml;
        }

        const summaryContainer = document.getElementById('payment-summary-container');
        
        // Handle PayPal (already rendered by PHP)
        if (isPayPal) {
            const paypalSummary = `
                <h4>Payment Summary</h4>
                <p><b>Transaction ID:</b> <?php echo htmlspecialchars($txn_id); ?></p>
                <p><b>Payment Status:</b> <?php echo htmlspecialchars($payment_status); ?></p>
                <h4 style="margin-top: 30px;">Order Details</h4>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Quantity</th>
                            <th>Total Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_items as $item) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                <td>$<?php echo number_format($item['total_price'], 2); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <h4 style="margin-top: 30px;">Grand Total: $<?php echo number_format($grand_total, 2); ?></h4>
                <p style="margin-top: 20px;">An email confirmation has been sent to <?php echo htmlspecialchars($customer_email); ?>.</p>
            `;
            summaryContainer.innerHTML = paypalSummary;
            // Clear the cart here as well to be safe
            localStorage.removeItem('shoppingCart');
        }

        // Handle Square and Google Pay (dynamically rendered by JS)
        if (isSquare || isGooglePay) {
            const urlParams = new URLSearchParams(window.location.search);
            const txnId = urlParams.get('txn_id') || `PAY_${Math.random().toString(16).slice(2, 10).toUpperCase()}`;
            const customerEmail = 'customer@example.com';
            
            const cart = JSON.parse(localStorage.getItem('shoppingCart'));
            if (cart) {
                const summaryHtml = populateSummary(txnId, 'Completed', customerEmail, cart);
                summaryContainer.innerHTML = summaryHtml;
            } else {
                summaryContainer.innerHTML = `<p>Order details could not be found. Please check your email for confirmation.</p>`;
            }
            localStorage.removeItem('shoppingCart');
        }

        // Handle Stripe Embedded Checkout
        if (isStripe) {
            const urlParams = new URLSearchParams(window.location.search);
            const sessionId = urlParams.get('session_id');

            const stripeLoading = `
                <h1>Payment <span id="status">Processing</span></h1>
                <p id="message">We're confirming your payment...</p>
                <div id="spinner" class="spinner-border text-primary" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
                <div id="stripe-summary-content" style="display:none;"></div>
            `;
            summaryContainer.innerHTML = stripeLoading;

            if (sessionId) {
                fetch(`http://localhost:4242/session-status?session_id=${sessionId}`)
                    .then(response => response.json())
                    .then(session => {
                        const statusElement = document.getElementById('status');
                        const messageElement = document.getElementById('message');
                        const spinnerElement = document.getElementById('spinner');
                        const summaryContentDiv = document.getElementById('stripe-summary-content');

                        if (session.status === 'complete') {
                            // CORRECTED: Read the cart inside the success callback
                            const cart = JSON.parse(localStorage.getItem('shoppingCart'));
                            const summaryHtml = populateSummary(
                                `STRIPE_TXN_${sessionId.substring(5, 15)}`,
                                'Completed',
                                session.customer_email,
                                cart
                            );
                            summaryContentDiv.innerHTML = summaryHtml;
                            summaryContentDiv.style.display = 'block';

                            statusElement.textContent = 'Successful';
                            statusElement.className = 'success';
                            messageElement.textContent = 'Thank you for your purchase!';
                            spinnerElement.style.display = 'none';
                            
                            // CORRECTED: Only clear the cart after a confirmed successful payment
                            localStorage.removeItem('shoppingCart');

                        } else {
                            statusElement.textContent = 'Failed';
                            statusElement.className = 'error';
                            messageElement.textContent = 'Payment was not successful. Please try again.';
                            spinnerElement.style.display = 'none';
                        }
                    })
                    .catch(error => {
                        console.error('Checkout initialization error:', error);
                        document.getElementById('status').textContent = 'Error';
                        document.getElementById('status').className = 'error';
                        document.getElementById('message').textContent = 'An error occurred while checking the payment status.';
                        document.getElementById('spinner').style.display = 'none';
                    });
            } else {
                document.getElementById('status').textContent = 'Error';
                document.getElementById('status').className = 'error';
                document.getElementById('message').textContent = 'No session ID found in the URL.';
                document.getElementById('spinner').style.display = 'none';
            }
        }
    });
</script>

</body>
</html>