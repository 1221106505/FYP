// Function to handle the payment submission
async function processPayment(event) {
    // Prevent the default form submission to handle it via JS/AJAX if desired
    // Or let the PHP handle the redirect as we planned earlier
    
    const name = document.querySelector('input[name="name"]').value;
    const number = document.querySelector('input[name="number"]').value;
    const method = document.querySelector('select[name="method"]').value;

    // Basic Validation
    if (!name || number.length < 10) {
        alert("Please provide valid contact details.");
        event.preventDefault(); // Stop form from submitting
        return;
    }

    // If using the Shopee-style "Select All" logic, 
    // ensure at least one item is checked (if you added checkboxes)
    console.log("Processing payment for:", name, "via", method);
}

// Optional: Dynamic Total calculation if the user toggles items
function updatePaymentDisplay() {
    const totalElement = document.getElementById("paymentTotal");
    if (totalElement) {
        // Retrieve the total amount stored during the cart phase
        const total = localStorage.getItem("totalAmount") || "0.00";
        totalElement.innerText = total;
    }
}

document.addEventListener("DOMContentLoaded", () => {
    updatePaymentDisplay();
    
    const paymentForm = document.querySelector('.checkout form');
    if (paymentForm) {
        paymentForm.addEventListener('submit', processPayment);
    }
})

async function loadPaymentSummary() {
    const summaryDiv = document.getElementById("checkoutItems");
    if (!summaryDiv) return;

    // Fetch the cart from the PHP session handler we built earlier
    const response = await fetch('cart_handler.php?action=get');
    const data = await response.json();
    const cart = data.cart || [];

    let total = 0;
    summaryDiv.innerHTML = "";

    cart.forEach(item => {
        const itemTotal = item.price * item.qty;
        total += itemTotal;
        summaryDiv.innerHTML += `
            <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                <span>${item.title} (x${item.qty})</span>
                <span>RM ${itemTotal.toFixed(2)}</span>
            </div>`;
    });

    document.getElementById("paymentTotal").innerText = total.toFixed(2);
}

document.addEventListener("DOMContentLoaded", loadPaymentSummary);