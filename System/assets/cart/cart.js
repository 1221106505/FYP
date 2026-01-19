// assets/cart/cart.js
// BookStore Cart Module — API + UI (no mock data)
// ✅ Fixes: API URL building, JSON parsing, 404/HTML response handling, button handlers, qty +/- working

const API_BASE = "../api/cart"; // adjust ONLY if your folder structure differs (see note at bottom)

let cartItems = [];
let savedItems = [];

const TAX_RATE = 0.06;

const SHIPPING_RULES = {
  standard: { fee: 5.00, minDays: 2, maxDays: 3, label: "Standard (Peninsular)" },
  express:  { fee: 12.00, minDays: 1, maxDays: 1, label: "Express (Peninsular)" },
  sabah:    { fee: 18.00, minDays: 4, maxDays: 7, label: "Sabah Delivery" },
  sarawak:  { fee: 16.00, minDays: 4, maxDays: 7, label: "Sarawak Delivery" }
};

// Shipping + Promo
let shippingOption = localStorage.getItem("cart_shipping") || "standard";
let promoCode = localStorage.getItem("cart_promo") || "";
let discountValue = 0;
let lastPaidReceipt = null; // will be filled only after successful payment


// -------------------- Boot --------------------
document.addEventListener("DOMContentLoaded", () => {
  // Top actions
  document.getElementById("selectAll")?.addEventListener("change", onSelectAll);
  document.getElementById("btnRemoveSelected")?.addEventListener("click", removeSelected);
  document.getElementById("btnClearCart")?.addEventListener("click", clearCart);
  document.getElementById("btnCheckout")?.addEventListener("click", checkout);

  // Shipping + Promo
  const shippingSelect = document.getElementById("shippingOption");
  const promoInput = document.getElementById("promo");
  const btnApplyPromo = document.getElementById("btnApplyPromo");

  if (shippingSelect) {
    if (!SHIPPING_RULES[shippingOption]) shippingOption = "standard";
    shippingSelect.value = shippingOption;
    shippingSelect.addEventListener("change", () => {
      shippingOption = shippingSelect.value;
      localStorage.setItem("cart_shipping", shippingOption);
      updateDeliveryEstimate();
      updateTotals();
    });
  }

  if (promoInput) promoInput.value = promoCode;
  btnApplyPromo?.addEventListener("click", applyPromo);

  updateDeliveryEstimate();

  // Receipt modal
  document.getElementById("btnPreviewReceipt")?.addEventListener("click", openReceiptModal);
  document.getElementById("btnPrintReceipt")?.addEventListener("click", printReceipt);
  document.getElementById("btnCloseReceipt")?.addEventListener("click", closeReceiptModal);
  document.getElementById("receiptBackdrop")?.addEventListener("click", closeReceiptModal);

  // Secure payment panel
  document.getElementById("togglePayment")?.addEventListener("click", togglePaymentPanel);
  document.getElementById("paymentMethod")?.addEventListener("change", syncPaymentFields);

  // Card live validation
  document.getElementById("cardNumber")?.addEventListener("input", onCardNumberInput);
  document.getElementById("cardExpiry")?.addEventListener("input", onCardExpiryInput);
  document.getElementById("cardCvv")?.addEventListener("input", onCardCvvInput);

  syncPaymentFields();
  setReceiptAvailability(false);
  setReceiptAvailability(true);

  // Load cart
  loadCart();
});

// -------------------- UI Helpers --------------------
function setMsg(text, isError = false) {
  const el = document.getElementById("msg");
  if (!el) return;
  el.textContent = text || "";
  el.style.color = isError ? "#ef4444" : "#6b7280";
}

function setReceiptAvailability(isPaid) {
  const viewBtn = document.getElementById("btnPreviewReceipt");
  const printBtn = document.getElementById("btnPrintReceipt");
  const hint = document.getElementById("receiptHint");

  if (viewBtn) viewBtn.disabled = !isPaid;
  if (printBtn) printBtn.disabled = !isPaid;

  if (hint) {
    hint.textContent = isPaid
      ? "Receipt is available. You can view or print it now."
      : "Receipt will be available after payment is completed.";
  }
}

function showState(message) {
  const state = document.getElementById("cartState");
  if (!state) return;
  state.style.display = "block";
  state.textContent = message || "";
}

function hideState() {
  const state = document.getElementById("cartState");
  if (!state) return;
  state.style.display = "none";
  state.textContent = "";
}

function escapeHtml(str) {
  return String(str).replace(/[&<>"']/g, (s) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#039;"
  }[s]));
}

function normalizeCode(code) {
  return (code || "").trim().toUpperCase();
}

function setPromoMsg(text, isError = false) {
  const el = document.getElementById("promoMsg");
  if (!el) return;
  el.textContent = text || "";
  el.style.color = isError ? "#ef4444" : "#6b7280";
}

// -------------------- API Helpers --------------------
// Robust JSON fetch that explains 404/HTML issues
async function apiGet(path) {
  const url = `${API_BASE}/${path}`;
  const res = await fetch(url, { cache: "no-store", credentials: "include" });

  const text = await res.text();

  if (!res.ok) {
    throw new Error(`GET ${url} failed (${res.status}). First 160 chars: ${text.substring(0, 160)}`);
  }

  try {
    return JSON.parse(text);
  } catch {
    throw new Error(`GET ${url} did not return JSON. First 160 chars: ${text.substring(0, 160)}`);
  }
}

async function apiPost(path, payload) {
  const url = `${API_BASE}/${path}`;
  const res = await fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload || {}),
    credentials: "include"
  });

  const text = await res.text();

  if (!res.ok) {
    throw new Error(`POST ${url} failed (${res.status}). First 160 chars: ${text.substring(0, 160)}`);
  }

  try {
    return JSON.parse(text);
  } catch {
    throw new Error(`POST ${url} did not return JSON. First 160 chars: ${text.substring(0, 160)}`);
  }
}

// -------------------- Load & Render --------------------
async function loadCart() {
  setMsg("");
  hideState();

  try {
    // ✅ Your real API endpoint
    const data = await apiGet("cart_get.php");

    if (!data || !data.success) {
      cartItems = [];
      savedItems = [];
      render();
      showState((data && data.error) ? data.error : "Unable to load cart (not logged in or server error).");
      return;
    }

    cartItems = Array.isArray(data.cart) ? data.cart : [];
    savedItems = Array.isArray(data.saved) ? data.saved : [];
    render();
  } catch (err) {
    console.error(err);
    cartItems = [];
    savedItems = [];
    render();
    showState("Failed to load cart. Open Console to see the exact API error.");
  }
}

function render() {
  renderList("cartList", cartItems, false);
  renderList("savedList", savedItems, true);
  updateSelectAllState();
  updateRemoveSelectedButton();
  updateTotals();
}

function renderList(containerId, items, isSaved) {
  const el = document.getElementById(containerId);
  if (!el) return;

  el.innerHTML = "";

  if (!items || items.length === 0) {
    el.innerHTML = `
      <div class="state" style="display:block">
        ${isSaved ? "No saved items." : "Your cart is empty. Add books to see them here."}
      </div>
    `;
    return;
  }

  for (const item of items) {
    const price = Number(item.price) || 0;
    const qty = Number(item.quantity) || 1;
    const lineTotal = price * qty;

    el.insertAdjacentHTML("beforeend", `
      <div class="item" data-id="${item.cart_id}">
        ${isSaved ? "" : `
          <input type="checkbox" class="rowCheck" data-id="${item.cart_id}" />
        `}

        <div class="meta">
          <div class="title">${escapeHtml(item.title)}</div>
          <div class="sub">RM ${price.toFixed(2)} • Book ID: ${item.book_id}</div>
        </div>

        <div class="right">
          <div class="price">RM ${lineTotal.toFixed(2)}</div>

          ${isSaved ? `
            <div class="item-actions">
              <button type="button" class="btn" onclick="moveBack(${item.cart_id})">Move to Cart</button>
              <button type="button" class="btn danger" onclick="removeOne(${item.cart_id})">Remove</button>
            </div>
          ` : `
            <div class="qty">
              <button type="button" onclick="changeQty(${item.cart_id}, -1)">−</button>
              <input type="number" min="1" value="${qty}" onchange="setQty(${item.cart_id}, this.value)" />
              <button type="button" onclick="changeQty(${item.cart_id}, 1)">+</button>
            </div>

            <div class="item-actions">
              <button type="button" class="btn" onclick="saveForLater(${item.cart_id})">Save for Later</button>
              <button type="button" class="btn danger" onclick="removeOne(${item.cart_id})">Remove</button>
            </div>
          `}
        </div>
      </div>
    `);
  }

  // Only cart list has selection checkboxes
  if (!isSaved) {
    el.querySelectorAll(".rowCheck").forEach(cb => cb.addEventListener("change", () => {
      updateSelectAllState();
      updateRemoveSelectedButton();
    }));
  }
}

// -------------------- Selection --------------------
function onSelectAll(e) {
  const checked = !!e.target.checked;
  document.querySelectorAll(".rowCheck").forEach(cb => (cb.checked = checked));
  updateRemoveSelectedButton();
  updateSelectAllState();
}

function updateSelectAllState() {
  const checks = Array.from(document.querySelectorAll(".rowCheck"));
  const selectAll = document.getElementById("selectAll");
  if (!selectAll) return;

  if (checks.length === 0) {
    selectAll.checked = false;
    selectAll.indeterminate = false;
    return;
  }

  const checkedCount = checks.filter(c => c.checked).length;
  selectAll.checked = checkedCount === checks.length;
  selectAll.indeterminate = checkedCount > 0 && checkedCount < checks.length;
}

function getSelectedIds() {
  return Array.from(document.querySelectorAll(".rowCheck"))
    .filter(cb => cb.checked)
    .map(cb => Number(cb.dataset.id));
}

function updateRemoveSelectedButton() {
  const btn = document.getElementById("btnRemoveSelected");
  if (!btn) return;
  btn.disabled = getSelectedIds().length === 0;
}

// -------------------- Promo + Delivery --------------------
function applyPromo() {
  const promoInput = document.getElementById("promo");
  promoCode = normalizeCode(promoInput ? promoInput.value : "");
  localStorage.setItem("cart_promo", promoCode);

  updateTotals();

  if (!promoCode) {
    setPromoMsg("Promo cleared.");
    return;
  }

  const valid = ["SAVE10", "SAVE20", "FREESHIP"].includes(promoCode);
  setPromoMsg(valid ? `Promo applied: ${promoCode}` : "Invalid promo code.", !valid);
}

function updateDeliveryEstimate() {
  const el = document.getElementById("deliveryEstimate");
  if (!el) return;

  const rule = SHIPPING_RULES[shippingOption] || SHIPPING_RULES.standard;

  const now = new Date();
  const d1 = new Date(now);
  d1.setDate(now.getDate() + rule.minDays);

  const d2 = new Date(now);
  d2.setDate(now.getDate() + rule.maxDays);

  const fmt = (d) => d.toLocaleDateString(undefined, { year: "numeric", month: "short", day: "numeric" });

  el.textContent = (rule.minDays === rule.maxDays)
    ? `Estimated delivery: ${fmt(d1)} • ${rule.label}`
    : `Estimated delivery: ${fmt(d1)} – ${fmt(d2)} • ${rule.label}`;
}


function updateTotals() {
  const itemsCount = cartItems.reduce((acc, it) => acc + (Number(it.quantity) || 0), 0);

  const subtotal = cartItems.reduce((acc, it) => {
    const price = Number(it.price) || 0;
    const qty = Number(it.quantity) || 0;
    return acc + price * qty;
  }, 0);

  // shipping depends on option
  let shipping = 0;
if (cartItems.length > 0) {
  const rule = SHIPPING_RULES[shippingOption] || SHIPPING_RULES.standard;
  shipping = rule.fee;
}


  // discount rules
  const code = normalizeCode(promoCode);
  discountValue = 0;

  if (code === "SAVE10") discountValue = subtotal * 0.10;
  else if (code === "SAVE20") discountValue = subtotal * 0.20;
  else if (code === "FREESHIP") discountValue = shipping;

  if (discountValue > (subtotal + shipping)) discountValue = (subtotal + shipping);

  const taxableBase = Math.max(0, subtotal - discountValue);
  const tax = taxableBase * TAX_RATE;
  const grand = Math.max(0, taxableBase + shipping + tax);

  document.getElementById("itemsCount") && (document.getElementById("itemsCount").textContent = String(itemsCount));
  document.getElementById("subtotal") && (document.getElementById("subtotal").textContent = subtotal.toFixed(2));
  document.getElementById("shipping") && (document.getElementById("shipping").textContent = shipping.toFixed(2));
  document.getElementById("tax") && (document.getElementById("tax").textContent = tax.toFixed(2));
  document.getElementById("grandTotal") && (document.getElementById("grandTotal").textContent = grand.toFixed(2));

  const discountRow = document.getElementById("discountRow");
  const elDiscount = document.getElementById("discount");

  if (discountRow && elDiscount) {
    if (discountValue > 0.001) {
      discountRow.style.display = "flex";
      elDiscount.textContent = discountValue.toFixed(2);
    } else {
      discountRow.style.display = "none";
      elDiscount.textContent = "0.00";
    }
  }
}

// -------------------- Cart Actions (API) --------------------
async function setQty(cartId, qty) {
  qty = Number(qty);

  if (!Number.isFinite(qty) || qty < 1) {
    await loadCart();
    return;
  }

  try {
    const data = await apiPost("cart_update.php", { cart_id: cartId, quantity: qty });
    if (!data.success) setMsg(data.error || "Failed to update quantity.", true);
  } catch (err) {
    console.error(err);
    setMsg("Quantity update failed. Check Console for details.", true);
  }

  await loadCart();
}

async function changeQty(cartId, delta) {
  const item = cartItems.find(i => Number(i.cart_id) === Number(cartId));
  if (!item) return;

  const current = Number(item.quantity) || 1;
  const next = current + Number(delta);
  if (next < 1) return;

  await setQty(cartId, next);
}

async function removeOne(cartId) {
  if (!confirm("Remove this item?")) return;

  try {
    const data = await apiPost("cart_remove.php", { cart_ids: [cartId] });
    if (!data.success) setMsg(data.error || "Failed to remove item.", true);
  } catch (err) {
    console.error(err);
    setMsg("Remove failed. Check Console for details.", true);
  }

  await loadCart();
}

async function removeSelected() {
  const ids = getSelectedIds();
  if (ids.length === 0) return;

  if (!confirm(`Remove ${ids.length} selected item(s)?`)) return;

  try {
    const data = await apiPost("cart_remove.php", { cart_ids: ids });
    if (!data.success) setMsg(data.error || "Failed to remove selected.", true);
  } catch (err) {
    console.error(err);
    setMsg("Remove selected failed. Check Console for details.", true);
  }

  await loadCart();
}

async function saveForLater(cartId) {
  try {
    const data = await apiPost("cart_toggle_save.php", { cart_id: cartId, saved: 1 });
    if (!data.success) setMsg(data.error || "Failed to save item.", true);
  } catch (err) {
    console.error(err);
    setMsg("Save for later failed. Check Console for details.", true);
  }

  await loadCart();
}

async function moveBack(cartId) {
  try {
    const data = await apiPost("cart_toggle_save.php", { cart_id: cartId, saved: 0 });
    if (!data.success) setMsg(data.error || "Failed to move item back.", true);
  } catch (err) {
    console.error(err);
    setMsg("Move back failed. Check Console for details.", true);
  }

  await loadCart();
}

async function clearCart() {
  if (!confirm("Clear all cart items?")) return;

  try {
    const data = await apiPost("cart_clear.php", {});
    if (!data.success) setMsg(data.error || "Failed to clear cart.", true);
  } catch (err) {
    console.error(err);
    setMsg("Clear cart failed. Check Console for details.", true);
  }

  await loadCart();
}

// -------------------- Secure Payment UI --------------------
function togglePaymentPanel() {
  const panel = document.getElementById("paymentPanel");
  const icon = document.getElementById("toggleIcon");
  const btn = document.getElementById("togglePayment");
  if (!panel || !icon || !btn) return;

  const isOpen = panel.style.display !== "none";
  panel.style.display = isOpen ? "none" : "block";
  btn.setAttribute("aria-expanded", String(!isOpen));
  icon.textContent = isOpen ? "＋" : "－";
}

function syncPaymentFields() {
  const card = document.getElementById("cardFields");
  const bank = document.getElementById("bankFields");
  const cod = document.getElementById("codFields");

  if (card) card.style.display = "block";
  if (bank) bank.style.display = "none";
  if (cod) cod.style.display = "none";

  setPaymentError("");
}


function setPaymentError(msg, isError = true) {
  const el = document.getElementById("paymentError");
  if (!el) return;
  el.textContent = msg || "";
  el.style.color = isError ? "#ef4444" : "#6b7280";
}

function setFieldMsg(id, msg, isError = false) {
  const el = document.getElementById(id);
  if (!el) return;
  el.textContent = msg || "";
  el.style.color = isError ? "#ef4444" : "#6b7280";
}

function digitsOnly(s) {
  return (s || "").replace(/\D/g, "");
}

function formatCardNumber(value) {
  const digits = digitsOnly(value).slice(0, 16);
  return digits.replace(/(\d{4})(?=\d)/g, "$1 ").trim();
}

function onCardNumberInput(e) {
  const formatted = formatCardNumber(e.target.value);
  e.target.value = formatted;

  const digits = digitsOnly(formatted);
  if (digits.length === 0) { setFieldMsg("cardNumberMsg", ""); return; }
  if (digits.length < 16) { setFieldMsg("cardNumberMsg", "Card number must be 16 digits.", true); return; }

  const ok = luhnCheck(digits);
  setFieldMsg("cardNumberMsg", ok ? "Card number looks valid." : "Invalid card number (Luhn failed).", !ok);
}

function onCardExpiryInput(e) {
  let v = digitsOnly(e.target.value).slice(0, 4);
  if (v.length >= 3) v = v.slice(0, 2) + "/" + v.slice(2);
  e.target.value = v;

  if (!v) { setFieldMsg("cardExpiryMsg", ""); return; }
  const valid = validateExpiry(v);
  setFieldMsg("cardExpiryMsg", valid ? "Expiry is valid." : "Invalid expiry (MM/YY).", !valid);
}

function onCardCvvInput(e) {
  let v = digitsOnly(e.target.value).slice(0, 4);
  e.target.value = v;

  if (!v) { setFieldMsg("cardCvvMsg", ""); return; }
  const ok = v.length === 3 || v.length === 4;
  setFieldMsg("cardCvvMsg", ok ? "CVV ok." : "CVV must be 3 or 4 digits.", !ok);
}

function luhnCheck(numStr) {
  let sum = 0;
  let shouldDouble = false;
  for (let i = numStr.length - 1; i >= 0; i--) {
    let digit = parseInt(numStr[i], 10);
    if (shouldDouble) {
      digit *= 2;
      if (digit > 9) digit -= 9;
    }
    sum += digit;
    shouldDouble = !shouldDouble;
  }
  return sum % 10 === 0;
}

function validateExpiry(mmYY) {
  const m = mmYY.match(/^(\d{2})\/(\d{2})$/);
  if (!m) return false;

  const mm = parseInt(m[1], 10);
  const yy = parseInt(m[2], 10);
  if (mm < 1 || mm > 12) return false;

  const now = new Date();
  const currentYY = now.getFullYear() % 100;
  const currentMM = now.getMonth() + 1;

  if (yy < currentYY) return false;
  if (yy === currentYY && mm < currentMM) return false;
  return true;
}

function getMaskedCardInfo() {
  const digits = digitsOnly(document.getElementById("cardNumber")?.value || "");
  if (digits.length < 4) return "";
  const last4 = digits.slice(-4);
  return `**** **** **** ${last4}`;
}

function validatePaymentBeforeCheckout() {
  const method = document.getElementById("paymentMethod")?.value || "Card";

  if (method === "COD") {
    setPaymentError("", false);
    return { ok: true, method, ref: "COD-" + Date.now() };
  }

  if (method === "BANK") {
    const ref = localStorage.getItem("cart_bank_ref") || "";
    if (!ref) {
      setPaymentError("Please generate a bank transfer reference first.", true);
      return { ok: false };
    }
    setPaymentError("", false);
    return { ok: true, method, ref };
  }

  // Card
  const name = (document.getElementById("cardName")?.value || "").trim();
  const number = digitsOnly(document.getElementById("cardNumber")?.value || "");
  const exp = (document.getElementById("cardExpiry")?.value || "").trim();
  const cvv = digitsOnly(document.getElementById("cardCvv")?.value || "");

  if (name.length < 3) return (setPaymentError("Please enter cardholder name.", true), { ok: false });
  if (number.length !== 16 || !luhnCheck(number)) return (setPaymentError("Please enter a valid 16-digit card number.", true), { ok: false });
  if (!validateExpiry(exp)) return (setPaymentError("Please enter a valid expiry (MM/YY).", true), { ok: false });
  if (!(cvv.length === 3 || cvv.length === 4)) return (setPaymentError("Please enter a valid CVV (3 or 4 digits).", true), { ok: false });

  setPaymentError("", false);
  return { ok: true, method, maskedCard: getMaskedCardInfo(), exp, ref: "CARD-" + Date.now() };
}

// -------------------- Bank Transfer Helpers --------------------
function generateBankRef() {
  const ref =
    "BS-" +
    new Date().toISOString().slice(0, 10).replaceAll("-", "") +
    "-" +
    Math.random().toString(36).slice(2, 6).toUpperCase();

  localStorage.setItem("cart_bank_ref", ref);

  const el = document.getElementById("bankRef");
  const status = document.getElementById("bankStatus");

  if (el) el.textContent = ref;
  if (status) status.textContent = "Reference generated. Include this in your bank transfer remark.";

  setPaymentError("", false);
}

async function copyText(id) {
  const text = document.getElementById(id)?.textContent?.trim();
  if (!text) return;

  try {
    await navigator.clipboard.writeText(text);
    setPaymentError("Copied to clipboard.", false);
    setTimeout(() => setPaymentError(""), 1200);
  } catch {
    alert("Copy failed. Please copy manually: " + text);
  }
}

// -------------------- Checkout --------------------
async function checkout() {
  setMsg("");

  if (cartItems.length === 0) {
    setMsg("Your cart is empty.", true);
    return;
  }

  const payment_method = "Card";
  const address = document.getElementById("address")?.value?.trim() || "";

  if (address.length < 8) {
    setMsg("Please enter a valid delivery address.", true);
    return;
  }

  const pay = validatePaymentBeforeCheckout();
  if (!pay.ok) return;

  try {
    const data = await apiPost("cart_checkout.php", { payment_method, address });

    if (!data.success) {
      setMsg(data.error || "Checkout failed.", true);
      return;
    }

    // ✅ Mark as paid + enable receipt buttons
lastPaidReceipt = {
  order_id: data.order_id,
  paid_at: new Date().toLocaleString(),
  payment_method: "Card",
  payment_ref: "CARD-" + Date.now(),
  masked_card: getMaskedCardInfo()
};

setReceiptAvailability(true);

    alert(`Order placed successfully! Order ID: ${data.order_id}`);
    await loadCart();
  } catch (err) {
    console.error(err);
    setMsg("Checkout failed. Check Console for exact API error.", true);
  }
}

// -------------------- Receipt --------------------
function openReceiptModal() {
  const modal = document.getElementById("receiptModal");
  const content = document.getElementById("receiptContent");
  if (!modal || !content) return;

  content.innerHTML = buildReceiptHTML();
  modal.classList.add("show");
  modal.setAttribute("aria-hidden", "false");
}

function closeReceiptModal() {
  const modal = document.getElementById("receiptModal");
  if (!modal) return;
  modal.classList.remove("show");
  modal.setAttribute("aria-hidden", "true");
}

function getTotalsForReceipt() {
  const subtotal = cartItems.reduce((acc, it) => {
    const price = Number(it.price) || 0;
    const qty = Number(it.quantity) || 0;
    return acc + price * qty;
  }, 0);

  let shipping = 0;
if (cartItems.length > 0) {
  const rule = SHIPPING_RULES[shippingOption] || SHIPPING_RULES.standard;
  shipping = rule.fee;
}

  const code = normalizeCode(promoCode);
  let discount = 0;

  if (code === "SAVE10") discount = subtotal * 0.10;
  else if (code === "SAVE20") discount = subtotal * 0.20;
  else if (code === "FREESHIP") discount = shipping;

  if (discount > (subtotal + shipping)) discount = (subtotal + shipping);

  const taxableBase = Math.max(0, subtotal - discount);
  const tax = taxableBase * TAX_RATE;
  const total = Math.max(0, taxableBase + shipping + tax);

  return { subtotal, shipping, discount, tax, total, code };
}

function buildReceiptHTML() {
  if (!lastPaidReceipt) {
  return `<div class="muted">Receipt not available yet. Please complete payment first.</div>`;
}
  const now = new Date();
  const receiptId =
    `DRAFT-${now.getFullYear()}${String(now.getMonth() + 1).padStart(2, "0")}${String(now.getDate()).padStart(2, "0")}` +
    `-${String(now.getHours()).padStart(2, "0")}${String(now.getMinutes()).padStart(2, "0")}${String(now.getSeconds()).padStart(2, "0")}`;

  const payment = document.getElementById("paymentMethod")?.value || "Card";
  const bankRef = localStorage.getItem("cart_bank_ref") || "";

  const address = document.getElementById("address")?.value?.trim() || "(Not provided yet)";
  const deliveryText = document.getElementById("deliveryEstimate")?.textContent || "";

  const t = getTotalsForReceipt();

  const itemsRows = cartItems.length
    ? cartItems.map(it => {
      const title = escapeHtml(it.title);
      const price = Number(it.price) || 0;
      const qty = Number(it.quantity) || 0;
      const line = price * qty;
      return `
        <tr>
          <td><strong>${title}</strong><div class="muted">Book ID: ${it.book_id}</div></td>
          <td>${qty}</td>
          <td>RM ${price.toFixed(2)}</td>
          <td><strong>RM ${line.toFixed(2)}</strong></td>
        </tr>
      `;
    }).join("")
    : `<tr><td colspan="4" class="muted">No items in cart yet.</td></tr>`;

  return `
    <div class="receipt">
      <h2>BookStore Receipt</h2>
      <div class="muted">Receipt ID: <strong>${receiptId}</strong></div>
      <div class="muted">Generated: ${now.toLocaleString()}</div>

      <div class="receipt-grid">
        <div class="receipt-box">
          <div class="muted">Payment Method</div>
          <div><strong>${escapeHtml(payment)}</strong></div>
          ${payment === "BANK" ? `<div class="muted">Reference: <strong>${escapeHtml(bankRef || "—")}</strong></div>` : ""}
        </div>
        <div class="receipt-box">
          <div class="muted">Shipping Option</div>
          <div><strong>${(SHIPPING_RULES[shippingOption] || SHIPPING_RULES.standard).label}</strong></div>
          <div class="muted">${escapeHtml(deliveryText)}</div>
        </div>
        <div class="receipt-box" style="grid-column: 1 / -1;">
          <div class="muted">Delivery Address</div>
          <div><strong>${escapeHtml(address)}</strong></div>
        </div>
      </div>

      <table class="receipt-table">
        <thead>
          <tr>
            <th style="width:55%;">Item</th>
            <th style="width:10%;">Qty</th>
            <th style="width:15%;">Unit</th>
            <th style="width:20%;">Total</th>
          </tr>
        </thead>
        <tbody>${itemsRows}</tbody>
      </table>

      <div class="receipt-grid">
        <div class="receipt-box">
          <div class="muted">Promo Code</div>
          <div><strong>${t.code ? escapeHtml(t.code) : "—"}</strong></div>
        </div>

        <div class="receipt-box">
          <div class="muted">Summary</div>
          <div>Subtotal: <strong>RM ${t.subtotal.toFixed(2)}</strong></div>
          <div>Shipping: <strong>RM ${t.shipping.toFixed(2)}</strong></div>
          <div>Discount: <strong>- RM ${t.discount.toFixed(2)}</strong></div>
          <div>Tax (6%): <strong>RM ${t.tax.toFixed(2)}</strong></div>
          <div style="margin-top:6px; font-size:16px;">Grand Total: <strong>RM ${t.total.toFixed(2)}</strong></div>
        </div>
      </div>

      <div class="muted" style="margin-top:10px;">
        Note: This preview is generated client-side.
      </div>
    </div>
  `;
}

function printReceipt() {
  if (!lastPaidReceipt) {
  alert("Receipt is only available after payment is completed.");
  return;
}
  const html = buildReceiptHTML();

  const w = window.open("", "_blank");
  if (!w) {
    alert("Popup blocked. Please allow popups to print/save PDF.");
    return;
  }

  w.document.open();
  w.document.write(`
    <html>
      <head>
        <title>Receipt</title>
        <style>
          body{font-family:Arial, sans-serif; margin:20px; color:#111827;}
          .muted{color:#6b7280;}
          .receipt{border:1px solid #e5e7eb; border-radius:14px; padding:16px;}
          table{width:100%; border-collapse:collapse; margin-top:12px;}
          th, td{border-bottom:1px solid #e5e7eb; padding:10px 8px; text-align:left; vertical-align:top;}
          th{background:#fafafa;}
          .receipt-grid{display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-top:10px;}
          .receipt-box{border:1px solid #e5e7eb; border-radius:12px; padding:10px 12px;}
          @media print { button{display:none;} }
        </style>
      </head>
      <body>
        ${html}
        <script>window.onload = () => window.print();</script>
      </body>
    </html>
  `);
  w.document.close();
}

// -------------------- Expose for inline onclick --------------------
window.setQty = setQty;
window.changeQty = changeQty;
window.removeOne = removeOne;
window.saveForLater = saveForLater;
window.moveBack = moveBack;
window.removeSelected = removeSelected;
window.clearCart = clearCart;
window.checkout = checkout;

/*
IMPORTANT NOTE (only if you get 404):
If your cart page is at: /cart/cart.html
and api is at: /api/cart/...
then API_BASE should be: "../api/cart" (current)

If your cart page is deeper (example /cart/pages/cart.html),
then change API_BASE to: "../../api/cart"
*/
