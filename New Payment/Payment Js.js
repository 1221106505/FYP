// 🛒 Example book cart (you can later link this to your actual product page)
let cart = JSON.parse(localStorage.getItem("cart")) || [
  { title: "Harry Potter", qty: 1, price: 30 },
  { title: "The Hobbit", qty: 2, price: 25 }
];

// 🧾 Display Cart
if (document.getElementById("cartItems")) {
  const cartDiv = document.getElementById("cartItems");
  let total = 0;

  cart.forEach(item => {
    const div = document.createElement("div");
    div.classList.add("cart-item");
    div.innerHTML = `<p>${item.title} (x${item.qty}) - RM${item.price * item.qty}</p>`;
    cartDiv.appendChild(div);
    total += item.price * item.qty;
  });

  document.getElementById("totalAmount").innerText = total.toFixed(2);
  localStorage.setItem("totalAmount", total);
}

// 💳 Payment Page
if (document.getElementById("paymentTotal")) {
  const total = localStorage.getItem("totalAmount");
  document.getElementById("paymentTotal").innerText = total;
}

// ✅ Confirm Payment
function confirmPayment() {
  const name = document.getElementById("cardName").value;
  const card = document.getElementById("cardNumber").value;
  const cvv = document.getElementById("cvv").value;
  const total = localStorage.getItem("totalAmount");

  if (name && card && cvv) {
    const history = JSON.parse(localStorage.getItem("history")) || [];
    history.push({
      name: name,
      total: total,
      date: new Date().toLocaleString()
    });
    localStorage.setItem("history", JSON.stringify(history));
    alert("Payment Successful! Thank you for your purchase.");
    window.location.href = "PaymentHistory.html";
  } else {
    alert("Please fill in all payment details.");
  }
}

// 📜 Load History
if (document.getElementById("historyList")) {
  const history = JSON.parse(localStorage.getItem("history")) || [];
  const list = document.getElementById("historyList");

  if (history.length === 0) {
    list.innerHTML = "<p>No purchase history found.</p>";
  } else {
    history.forEach(item => {
      const div = document.createElement("div");
      div.classList.add("history-item");
      div.innerHTML = `<p><strong>${item.name}</strong> paid RM${item.total} on ${item.date}</p>`;
      list.appendChild(div);
    });
  }
}

// 🔁 Navigation
function goToPayment() {
  window.location.href = "Payment.html";
}
function goBack() {
  window.history.back();
}
