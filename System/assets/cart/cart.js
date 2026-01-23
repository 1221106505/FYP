// assets/cart/cart.js
// BookStore Cart Module — API + UI (no mock data)
// ✅ Fixes: API URL building, JSON parsing, 404/HTML response handling, button handlers, qty +/- working

const API_BASE = "../api/cart"; // adjust ONLY if your folder structure differs (see note at bottom)

let cartItems = [];
let savedItems = [];

// ✅ Keep selected items even after re-render / loadCart
let selectedCartIds = new Set(
  JSON.parse(localStorage.getItem("cart_selected_ids") || "[]").map(Number)
);

function saveSelected() {
  localStorage.setItem("cart_selected_ids", JSON.stringify([...selectedCartIds]));
}

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


// -------------------- Boot --------------------
document.addEventListener("DOMContentLoaded", () => {
  // Top actions
  document.getElementById("selectAll")?.addEventListener("change", onSelectAll);
  document.getElementById("btnRemoveSelected")?.addEventListener("click", removeSelected);
  document.getElementById("btnClearCart")?.addEventListener("click", clearCart);
  document.getElementById("btnPayNow")?.addEventListener("click", payNow);

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

  // Secure payment panel
  document.getElementById("togglePayment")?.addEventListener("click", togglePaymentPanel);

  // Card live validation
  document.getElementById("cardNumber")?.addEventListener("input", onCardNumberInput);
  document.getElementById("cardExpiry")?.addEventListener("input", onCardExpiryInput);
  document.getElementById("cardCvv")?.addEventListener("input", onCardCvvInput);
    // Re-validate card number when user changes card type
  document.getElementById("cardType")?.addEventListener("change", () => {
    const el = document.getElementById("cardNumber");
    if (el) onCardNumberInput({ target: el });
  });


  syncPaymentFields();
 

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

function setRecipientMsg(msg, isError=false){
  const el = document.getElementById("recipientMsg");
  if(!el) return;
  el.textContent = msg || "";
  el.style.color = isError ? "#ef4444" : "#6b7280";
}
function setAddrMsg(id, msg, isError=false){
  const el = document.getElementById(id);
  if(!el) return;
  el.textContent = msg || "";
  el.style.color = isError ? "#ef4444" : "#6b7280";
}

function getRecipientName(){
  return (document.getElementById("recipientName")?.value || "").trim();
}

function getAddressFields(){
  return {
    street: (document.getElementById("street")?.value || "").trim(),
    area: (document.getElementById("area")?.value || "").trim(),
    city: (document.getElementById("city")?.value || "").trim(),
    state: (document.getElementById("state")?.value || "").trim(),
    postcode: (document.getElementById("postcode")?.value || "").trim(),
  };
}

function validateAddressFields(){
  const recipient = getRecipientName();
  const a = getAddressFields();
  let ok = true;

  if(recipient.length < 3){ setRecipientMsg("Please enter recipient full name.", true); ok=false; }
  else setRecipientMsg("");

  if(a.street.length < 5){ setAddrMsg("streetMsg","Please enter street/address line.", true); ok=false; }
  else setAddrMsg("streetMsg","");

  if(a.city.length < 2){ setAddrMsg("cityMsg","Please enter city.", true); ok=false; }
  else setAddrMsg("cityMsg","");

  if(a.state.length < 2){ setAddrMsg("stateMsg","Please enter state.", true); ok=false; }
  else setAddrMsg("stateMsg","");

  const pcDigits = a.postcode.replace(/\D/g,'');
  if(pcDigits.length < 4){ setAddrMsg("postcodeMsg","Please enter a valid postcode.", true); ok=false; }
  else setAddrMsg("postcodeMsg","");

  return ok;
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

// ✅ remove selected IDs that are no longer in cart
const existing = new Set(cartItems.map(it => Number(it.cart_id)));
selectedCartIds = new Set([...selectedCartIds].filter(id => existing.has(id)));
saveSelected();

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
          ${(() => {
  const cid = Number(item.cart_id);
  const checked = selectedCartIds.has(cid) ? "checked" : "";
  return `<input type="checkbox" class="rowCheck" data-id="${cid}" ${checked} />`;
})()}

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
  const id = Number(cb.dataset.id);

  if (cb.checked) selectedCartIds.add(id);
  else selectedCartIds.delete(id);

  saveSelected();
  updateSelectAllState();
  updateRemoveSelectedButton();
  updateTotals();
}));
  }
}

function getSelectedCartItems() {
  const selectedIds = getSelectedIds();
  if (selectedIds.length === 0) return [];
  return cartItems.filter(it => selectedIds.includes(Number(it.cart_id)));
}

// -------------------- Selection --------------------
function onSelectAll(e) {
  const checked = !!e.target.checked;

  document.querySelectorAll(".rowCheck").forEach(cb => {
    cb.checked = checked;
    const id = Number(cb.dataset.id);

    if (checked) selectedCartIds.add(id);
    else selectedCartIds.delete(id);
  });

  saveSelected();
  updateRemoveSelectedButton();
  updateSelectAllState();
  updateTotals();
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
  return [...selectedCartIds];
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
  const selectedItems = getSelectedCartItems();
  const itemsToSum = selectedItems.length > 0 ? selectedItems : [];

  // ✅ count + subtotal based on selected only
  const itemsCount = itemsToSum.reduce((acc, it) => acc + (Number(it.quantity) || 0), 0);

  const subtotal = itemsToSum.reduce((acc, it) => {
    const price = Number(it.price) || 0;
    const qty = Number(it.quantity) || 0;
    return acc + price * qty;
  }, 0);

  // ✅ shipping is 0 if nothing selected
  let shipping = 0;
  if (itemsToSum.length > 0) {
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
  const selectedType = document.getElementById("cardType")?.value || "VISA";

  if (digits.length === 0) {
    setFieldMsg("cardNumberMsg", "");
    const typeMsg = document.getElementById("cardTypeMsg");
    if(typeMsg) typeMsg.textContent = "";
    return;
  }

  const detected = detectNetwork(digits);
  const typeMsg = document.getElementById("cardTypeMsg");
  if(typeMsg){
    typeMsg.textContent = detected ? `Detected: ${detected}` : "";
    typeMsg.style.color = "#6b7280";
  }

  const v = validateBySelectedCardType(selectedType, digits);
  setFieldMsg("cardNumberMsg", v.msg, !v.ok);
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

function inRange(num, a, b){ return num >= a && num <= b; }

function detectNetwork(digits){
  if(!digits) return "";
  if(digits.startsWith("4")) return "VISA";

  const first2 = parseInt(digits.slice(0,2),10);
  const first4 = parseInt(digits.slice(0,4),10);
  if(inRange(first2,51,55) || inRange(first4,2221,2720)) return "MASTERCARD";

  const f2 = parseInt(digits.slice(0,2),10);
  if(digits.startsWith("50") || inRange(f2,56,69)) return "DEBIT";

  if(digits.startsWith("34") || digits.startsWith("37") || digits.startsWith("6011") || digits.startsWith("65"))
    return "CREDIT";

  return "CREDIT";
}

function validateBySelectedCardType(selected, digits){
  if(digits.length < 13 || digits.length > 19) return { ok:false, msg:"Card number length must be 13–19 digits." };
  if(!luhnCheck(digits)) return { ok:false, msg:"Invalid card number (Luhn check failed)." };

  if(selected === "VISA"){
    const ok = digits.startsWith("4") && (digits.length===13 || digits.length===16 || digits.length===19);
    return { ok, msg: ok ? "VISA card validated." : "Selected VISA, but number must start with 4." };
  }

  if(selected === "MASTERCARD"){
    const first2 = parseInt(digits.slice(0,2),10);
    const first4 = parseInt(digits.slice(0,4),10);
    const ok = digits.length===16 && (inRange(first2,51,55) || inRange(first4,2221,2720));
    return { ok, msg: ok ? "MasterCard validated." : "Selected MasterCard, but prefix is not valid." };
  }

  if(selected === "DEBIT"){
    const first2 = parseInt(digits.slice(0,2),10);
    const ok = (digits.startsWith("50") || inRange(first2,56,69));
    return { ok, msg: ok ? "Debit card validated." : "Selected Debit, but prefix is not valid." };
  }

  return { ok:true, msg:"Credit card validated." };
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
    const selectedType = document.getElementById("cardType")?.value || "VISA";
  const cardTypeCheck = validateBySelectedCardType(selectedType, number);
  if (!cardTypeCheck.ok) return (setPaymentError(cardTypeCheck.msg, true), { ok: false });
  const exp = (document.getElementById("cardExpiry")?.value || "").trim();
  const cvv = digitsOnly(document.getElementById("cardCvv")?.value || "");

  if (name.length < 3) return (setPaymentError("Please enter cardholder name.", true), { ok: false });
  if (number.length < 13 || number.length > 19) return (setPaymentError("Card number length must be 13–19 digits.", true), { ok: false });
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

function setPayMsg(text, isError = false) {
  const el = document.getElementById("payMsg");
  if (!el) return;
  el.textContent = text || "";
  el.style.color = isError ? "#ef4444" : "#6b7280";
}

function getAddressValue() {
  return (document.getElementById("address")?.value || "").trim();
}

function setAddressMsg(msg, isError = false) {
  const el = document.getElementById("addressMsg");
  if (!el) return;
  el.textContent = msg || "";
  el.style.color = isError ? "#ef4444" : "#6b7280";
}


async function payNow() {
  setPayMsg("");

  const addr = getAddressFields();
const allEmpty = !addr.street && !addr.area && !addr.city && !addr.state && !addr.postcode;

if (allEmpty) {
  setPayMsg("Please enter an address (at least something).", true);
  return;
}

  // 2) Ensure cart has items (or you can check selected items if you want)
  if (cartItems.length === 0) {
    setPayMsg("Your cart is empty.", true);
    return;
  }

  // 3) Validate card details
  const pay = validatePaymentBeforeCheckout();
  if (!pay.ok) return;

  setPayMsg("Processing payment...");

  try {
    // ✅ Map your UI card type to DB enum values
    // orders.payment_method is enum like: credit_card / debit_card / etc
    const selectedType = (document.getElementById("cardType")?.value || "CREDIT").toUpperCase();
    const payment_method = (selectedType === "DEBIT") ? "debit_card" : "credit_card";

    const recipient_name = getRecipientName();
    const addr = getAddressFields();

const data = await apiPost("cart_checkout.php", {
  payment_method,
  recipient_name,
  street: addr.street,
  area: addr.area,
  city: addr.city,
  state: addr.state,
  postcode: addr.postcode
});


    if (!data || !data.success) {
      setPayMsg((data && data.error) ? data.error : "Checkout failed.", true);
      return;
    }

    await loadCart();

    // Extra safety: if server didn’t clear cart
    if (cartItems.length > 0) {
      await apiPost("cart_clear.php", {});
      await loadCart();
    }

    setPayMsg("");
openSuccessModal(data.order_id);

// ✅ Popup
alert(`✅ Payment Successful!\nYour Order ID is: ${data.order_id}`);
    showPaymentSuccessPopup(data.order_id);
  } catch (err) {
    console.error(err);
    setPayMsg("Payment failed. Check Console (F12).", true);
  }
}

// -------------------- Success Popup (Modal) --------------------
function openSuccessModal(orderId){
  const modal = document.getElementById("successModal");
  const text = document.getElementById("successModalText");
  const oid = document.getElementById("successOrderId");

  if(text) text.textContent = "Your order has been placed successfully. Thank you for shopping with us!";
  if(oid) oid.textContent = String(orderId);

  modal.classList.add("show");
  modal.setAttribute("aria-hidden", "false");

  document.addEventListener("keydown", escCloseOnce);
}

function escCloseOnce(e){
  if(e.key === "Escape"){
    closeSuccessModal();
    document.removeEventListener("keydown", escCloseOnce);
  }
}

function closeSuccessModal(){
  const modal = document.getElementById("successModal");
  if(!modal) return;
  modal.classList.remove("show");
  modal.setAttribute("aria-hidden", "true");
}

function copyOrderId(){
  const oid = document.getElementById("successOrderId")?.textContent?.trim();
  if(!oid) return;

  navigator.clipboard.writeText(oid).then(() => {
    const text = document.getElementById("successModalText");
    if(text){
      const old = text.textContent;
      text.textContent = "Order ID copied ✅";
      setTimeout(() => (text.textContent = old), 900);
    }
  });
}

function goOrders(){
  closeSuccessModal();
  window.location.href = "../order_history.html";
}

function goShop(){
  closeSuccessModal();
  window.location.href = "../customer_view.html";
}

// expose for onclick used in HTML modal
window.closeSuccessModal = closeSuccessModal;
window.copyOrderId = copyOrderId;
window.goOrders = goOrders;
window.goShop = goShop;

// -------------------- Expose for inline onclick --------------------
window.setQty = setQty;
window.changeQty = changeQty;
window.removeOne = removeOne;
window.saveForLater = saveForLater;
window.moveBack = moveBack;
window.removeSelected = removeSelected;
window.clearCart = clearCart;

/*
IMPORTANT NOTE (only if you get 404):
If your cart page is at: /cart/cart.html
and api is at: /api/cart/...
then API_BASE should be: "../api/cart" (current)

If your cart page is deeper (example /cart/pages/cart.html),
then change API_BASE to: "../../api/cart"
*/
