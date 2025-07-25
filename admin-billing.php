<?php
$host = "localhost";
$user = "root";
$pass = "";
$db = "dinesmart";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
$items = $conn->query("SELECT id, name, price FROM menu_items ORDER BY name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Billing - DineSmart</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .billing-container {
      max-width: 800px;
      margin: 50px auto;
      padding: 30px;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 0 12px rgba(0,0,0,0.1);
    }

    .billing-container h2 {
      text-align: center;
      color: #c0392b;
      margin-bottom: 20px;
    }

    select, input {
      padding: 10px;
      margin: 10px 5px;
      border-radius: 5px;
      border: 1px solid #ccc;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }

    table, th, td {
      border: 1px solid #ddd;
    }

    th, td {
      padding: 10px;
      text-align: center;
    }

    .btn {
      background: #27ae60;
      color: white;
      padding: 10px 15px;
      border: none;
      margin-top: 15px;
      border-radius: 5px;
      cursor: pointer;
    }

    .btn-pdf {
      background: #2980b9;
    }

    .total {
      font-weight: bold;
      font-size: 18px;
      text-align: right;
      margin-top: 10px;
    }
  </style>
</head>
<body>

<header>
  <h1>Billing</h1>
  <nav>
    <ul>
      <li><a href="index.html">Home</a></li>
      <li><a href="menu.php">View Menu</a></li>
      <li><a href="booktable.html">Book Table</a></li>
      <li><a href="admin-login.html">Admin Login</a></li>
    </ul>
  </nav>
</header>

<main>
  <div class="billing-container">
    <label for="booking-token">Booking Token</label>
<input type="text" id="booking-token" placeholder="Enter Booking Token" maxlength="8" style="text-transform:uppercase;">
<button class="btn" onclick="verifyToken()">Verify Token</button>

<div id="token-message" style="margin:10px 0; font-weight:bold;"></div>

    <h2>Generate Customer Bill</h2>

    <div>
      <select id="item-select">
        <option value="">-- Select Item --</option>
        <?php while ($row = $items->fetch_assoc()) {
          echo "<option value='{$row['id']}' data-price='{$row['price']}'>{$row['name']} (Rs. {$row['price']})</option>";
        } ?>
      </select>
      <input type="number" id="quantity" placeholder="Quantity" min="1">
      <button class="btn" onclick="addItem()">Add Item</button>
    </div>

    <table id="bill-table">
      <thead>
        <tr>
          <th>Item</th>
          <th>Price</th>
          <th>Qty</th>
          <th>Total</th>
          <th>Remove</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>

    <div class="total" id="grand-total">Grand Total: Rs. 0.00</div>

    <button class="btn btn-pdf" onclick="downloadPDF()">Download PDF</button>
  </div>
</main>

<footer>
  <p>&copy; 2025 DineSmart Restaurant. All rights reserved.</p>
</footer>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
let billItems = [];

function verifyToken() {
  const token = document.getElementById('booking-token').value.trim().toUpperCase();
  const msg = document.getElementById('token-message');

  if (!token) {
    msg.textContent = "Please enter a booking token.";
    msg.style.color = "red";
    return;
  }

  fetch('verify_token.php?token=' + token)
    .then(res => res.json())
    .then(data => {
      if (data.valid) {
        msg.textContent = Token valid! Name: ${data.name}, Phone: ${data.phone};
        msg.style.color = "green";
        window.verifiedToken = token;
        window.verifiedName = data.name;
        window.verifiedPhone = data.phone;
        window.verifiedPrepaid = data.prepaid;

        billItems = [];
        if (Array.isArray(data.items)) {
          data.items.forEach(item => {
            billItems.push({
              name: item.name,
              price: parseFloat(item.price),
              qty: parseInt(item.quantity),
              total: parseFloat(item.price) * parseInt(item.quantity)
            });
          });
        }
        updateTable();
      } else {
        msg.textContent = "Invalid token!";
        msg.style.color = "red";
        window.verifiedToken = null;
        billItems = [];
        updateTable();
      }
    })
    .catch(err => {
      msg.textContent = "Error verifying token.";
      msg.style.color = "red";
      console.error(err);
    });
}


function updateTable() {
  const tbody = document.querySelector("#bill-table tbody");
  tbody.innerHTML = "";
  let grandTotal = 0;

  billItems.forEach((item, index) => {
    const price = parseFloat(item.price);
    const total = parseFloat(item.total);

    if (isNaN(price) || isNaN(total)) return;

    grandTotal += total;

    tbody.innerHTML += `
      <tr>
        <td>${item.name}</td>
        <td>Rs. ${price.toFixed(2)}</td>
        <td>${item.qty}</td>
        <td>Rs. ${total.toFixed(2)}</td>
        <td><button onclick="removeItem(${index})">X</button></td>
      </tr>
    `;
  });

let adjustedTotal = grandTotal;
let prepaid = parseFloat(window.verifiedPrepaid) || 0;
adjustedTotal -= prepaid;
if (adjustedTotal < 0) adjustedTotal = 0;

document.getElementById("grand-total").innerHTML =
  `<div>Total: Rs. ${grandTotal.toFixed(2)}</div>
   <div>Prepaid (Advance): Rs. ${prepaid.toFixed(2)}</div>
   <div><strong>Amount to Pay: Rs. ${adjustedTotal.toFixed(2)}</strong></div>`;

}

function addItem() {
  if (!window.verifiedToken) {
    alert("Please verify your booking token before adding items.");
    return;
  }

  const select = document.getElementById("item-select");
  const qty = parseInt(document.getElementById("quantity").value);
  const id = select.value;
  const name = select.options[select.selectedIndex].text;
  const price = parseFloat(select.options[select.selectedIndex].getAttribute("data-price"));

  if (!id || !qty || qty <= 0) return alert("Select item and enter quantity.");

  const total = price * qty;
  billItems.push({ name, price, qty, total });
  updateTable();
}

function removeItem(index) {
  billItems.splice(index, 1);
  updateTable();
}
function downloadPDF() {
  if (!window.verifiedToken || billItems.length === 0) {
    alert('Please verify token and add items first.');
    return;
  }

  const container = document.createElement('div');
  container.style.padding = '20px';
 container.style.width = '100%'; // ✅ Make it full width
 container.style.minHeight = '500px'; // ✅ Add this
container.style.boxSizing = 'border-box'; // ✅ Ensure layout doesn't break
  let html = <h2 style="text-align:center;">DineSmart Restaurant - Customer Bill</h2>;
  html += <p><strong>Booking Token:</strong> ${window.verifiedToken}</p>;
  html += <p><strong>Customer Name:</strong> ${window.verifiedName}</p>;
  html += <p><strong>Phone Number:</strong> ${window.verifiedPhone}</p>;
  html += `<table border="1" cellspacing="0" cellpadding="5" width="100%" style="border-collapse:collapse;">
             <thead><tr>
               <th>Item</th><th>Price</th><th>Qty</th><th>Total</th>
             </tr></thead><tbody>`;

  let grandTotal = 0;

  billItems.forEach(item => {
    const itemTotal = item.price * item.qty;
    grandTotal += itemTotal;

    html += `<tr>
      <td>${item.name}</td>
      <td>Rs. ${item.price.toFixed(2)}</td>
      <td>${item.qty}</td>
      <td>Rs. ${itemTotal.toFixed(2)}</td>
    </tr>`;
  });

  const prepaid = parseFloat(window.verifiedPrepaid) || 0;
  let finalTotal = grandTotal - prepaid;
  if (finalTotal < 0) finalTotal = 0;

  html += `</tbody></table>
  <p><strong>Total:</strong> Rs. ${grandTotal.toFixed(2)}</p>
  <p><strong>Prepaid (Advance):</strong> Rs. ${prepaid.toFixed(2)}</p>
  <p><strong>Amount to Pay:</strong> Rs. ${finalTotal.toFixed(2)}</p>`;

  container.innerHTML = html;
  
  document.body.appendChild(container);

  setTimeout(() => {
    const opt = {
      margin: 0.5,
      filename: dinesmart_bill_${window.verifiedToken}.pdf,
      jsPDF: { unit: 'mm', format: 'a4' },
      html2canvas: { scale: 2 },
    };

    html2pdf().from(container).set(opt).save().then(() => {
      document.body.removeChild(container);
    });
  }, 800); // delay to ensure rendering
}

</script>

</body>
</html>