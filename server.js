// server.js

const express = require('express');
const stripe = require('stripe')('sk_test_51S6mx43RZaARmGsQvaeKhNYUQWrwHNDya9tG9fxrJJHMU7E2Zv3YBiSxAbAL7wCXkJ1FVuX5c8vUuptrgcoIPQZa00mVDFAiaP');
const bodyParser = require('body-parser');
const axios = require('axios');
const crypto = require('crypto');
const cors = require('cors');

const app = express();
const port = process.env.PORT || 4242;

// Square credentials (replace with your actual credentials from the Developer Dashboard)
// In a production app, use environment variables for security
const SQUARE_APPLICATION_ID = 'sandbox-sq0idb-d7QSfaR3gsMnFdKnsyfN_A';
const SQUARE_ACCESS_TOKEN = 'EAAAl3a99U2KeJL--GsaitZh67BjjoqfwTbka3EXmqgVemkUzlSMDv2r3tfKW71q';
const SQUARE_LOCATION_ID = 'L5FT5VENVA3Z4';

// Middleware to parse JSON bodies
app.use(bodyParser.json());
// Enable CORS for your Apache server's origin
app.use(cors({
  origin: 'http://localhost',
  credentials: true
}));
// Serve static files
app.use(express.static('.'));

// Existing Stripe endpoint
app.post('/create-checkout-session', async (req, res) => {
  try {
    const lineItems = [];
    for (const id in req.body.cart) {
      const item = req.body.cart[id];
      lineItems.push({
        price_data: {
          currency: 'aud',
          product_data: {
            name: item.name,
          },
          unit_amount: Math.round(item.price * 100),
        },
        quantity: item.qty,
      });
    }

    const session = await stripe.checkout.sessions.create({
      ui_mode: 'embedded',
      line_items: lineItems,
      mode: 'payment',
      return_url: `http://localhost/SecurePayment/success.php?session_id={CHECKOUT_SESSION_ID}`,
    });

    res.json({ clientSecret: session.client_secret });
  } catch (error) {
    console.error('Error creating checkout session:', error);
    res.status(500).json({ error: error.message });
  }
});

// New endpoint to process Square payments using axios
app.post('/process-square-payment', async (req, res) => {
  const { paymentToken, cart } = req.body;
  let grandTotal = 0;

  for (const id in cart) {
    const item = cart[id];
    grandTotal += item.price * item.qty;
  }

  // Amount is in the smallest currency unit (e.g., cents)
  const amountMoney = {
    amount: Math.round(grandTotal * 100),
    currency: 'AUD',
  };

  const idempotencyKey = crypto.randomBytes(16).toString('hex');

  const paymentBody = {
    source_id: paymentToken,
    idempotency_key: idempotencyKey,
    amount_money: amountMoney,
    location_id: SQUARE_LOCATION_ID,
  };

  try {
    const response = await axios.post(
      'https://connect.squareupsandbox.com/v2/payments',
      paymentBody,
      {
        headers: {
          'Authorization': `Bearer ${SQUARE_ACCESS_TOKEN}`,
          'Content-Type': 'application/json',
        }
      }
    );
    
    // Check if the payment was successful based on the API response
    if (response.data.payment) {
        res.status(200).json({ success: true, transaction_id: response.data.payment.id });
    } else {
        console.error('Square Payment Failed:', response.data.errors);
        res.status(400).json({ success: false, errors: response.data.errors });
    }
  } catch (error) {
    console.error('An error occurred during payment creation.');
    // Check if the error is from the API response
    if (error.response) {
      console.error('Square API Error:', error.response.data);
      res.status(500).json({ success: false, errors: error.response.data.errors });
    } else {
      console.error('Internal Server Error:', error.message);
      res.status(500).json({ success: false, errors: [{ detail: 'An unexpected error occurred.' }] });
    }
  }
});

// Existing session status endpoint for Stripe
app.get('/session-status', async (req, res) => {
  try {
    const session = await stripe.checkout.sessions.retrieve(req.query.session_id);

    res.send({
      status: session.status,
      customer_email: session.customer_details?.email || 'customer'
    });
  } catch (error) {
    console.error('Error retrieving session:', error);
    res.status(500).json({ error: error.message });
  }
});

app.listen(port, () => {
  console.log(`Server running on port ${port}`);
});