// Navigation menu handling
const bar = document.getElementById('bar');
const close = document.getElementById('clese');
const navbar = document.getElementById('navbar');

if (bar) {
    bar.addEventListener('click', () => {
        navbar.classList.toggle('active');
        document.getElementById('search-results').style.display = 'none';
        document.getElementById('suggestions').style.display = 'none';
    });
}

if (close) {
    close.addEventListener('click', () => {
        navbar.classList.remove('active');
    });
}

const navItems = document.querySelectorAll('#navbar li a');
navItems.forEach(item => {
    item.addEventListener('click', () => {
        navbar.classList.remove('active');
    });
});

document.addEventListener('click', (e) => {
    if (!navbar.contains(e.target) && !bar.contains(e.target) && navbar.classList.contains('active')) {
        navbar.classList.remove('active');
    }
});

// Update quantity and recalculate totals with stock validation
function updateQuantity(index, change) {
    const qtyInput = document.getElementById(`quantity-${index}`);
    const currentQty = parseInt(qtyInput.value);
    const maxQty = parseInt(qtyInput.dataset.max) || 100;
    let newQuantity = currentQty + change;
    
    if (newQuantity < 1) return;
    if (newQuantity > maxQty) {
        newQuantity = maxQty;
        showToast(`Maximum available quantity is ${maxQty}`);
    }
    
    qtyInput.value = newQuantity;
    
    const minusBtn = qtyInput.previousElementSibling;
    const plusBtn = qtyInput.nextElementSibling;
    if (minusBtn && plusBtn) {
        minusBtn.disabled = newQuantity <= 1;
        plusBtn.disabled = newQuantity >= maxQty;
    }
    
    const price = parseFloat(document.querySelector(`#cart-item-${index} .item-price`).dataset.price);
    const newSubtotal = price * newQuantity;
    document.getElementById(`subtotal-${index}`).textContent = `Ksh ${newSubtotal.toFixed(2)}`;
    
    updateCartTotal();
    
    fetch('update-cart-quantity.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `index=${index}&quantity=${newQuantity}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            if (data.max_quantity !== undefined) {
                qtyInput.dataset.max = data.max_quantity;
                qtyInput.max = data.max_quantity;
                if (plusBtn) plusBtn.disabled = newQuantity >= data.max_quantity;
                
                if (newQuantity > data.max_quantity) {
                    qtyInput.value = data.max_quantity;
                    showToast(`Stock reduced! Maximum available quantity is now ${data.max_quantity}`);
                    updateCartTotal();
                }
            }
            
            updateCartTotal();
            updateCartBadge();
            showToast("Quantity updated successfully!");
        } else {
            console.error("Error updating quantity:", data.message);
            showToast("Failed to update quantity: " + data.message);
            qtyInput.value = currentQty;
            updateCartTotal();
        }
    })
    .catch(error => {
        console.error("Error updating quantity:", error);
        showToast("Error updating quantity. Please try again.");
        qtyInput.value = currentQty;
        updateCartTotal();
    });
}

// Confirmation toast for removing items
function showConfirmationToast(message, confirmCallback) {
    let existingToast = document.getElementById("confirmation-toast");
    if (existingToast) existingToast.remove();

    const toast = document.createElement("div");
    toast.id = "confirmation-toast";
    toast.innerHTML = `
        <p>${message}</p>
        <div>
            <button id="confirm-remove">Yes</button>
            <button id="cancel-remove">No</button>
        </div>
    `;
    
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.classList.add("show");
        document.getElementById("confirm-remove").addEventListener("click", () => {
            toast.classList.remove("show");
            setTimeout(() => {
                toast.remove();
                confirmCallback();
            }, 300);
        });

        document.getElementById("cancel-remove").addEventListener("click", () => {
            toast.classList.remove("show");
            setTimeout(() => toast.remove(), 300);
        });
    }, 50);
}

// Remove item from cart
function removeFromCart(index) {
    showConfirmationToast("Are you sure you want to remove this item?", () => {
        fetch('remove-from_cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `index=${index}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.querySelector(`#cart-item-${index}`).remove();
                updateCartTotal();
                checkIfCartIsEmpty();
                
                document.getElementById('cart-count').textContent = data.cart_count;
                document.getElementById('cart-count-mobile').textContent = data.cart_count;
                
                showToast("Item removed from cart.");
            } else {
                console.error("Error removing item:", data.message);
                showToast("Error: " + (data.message || "Failed to remove item"), true);
            }
        })
        .catch(error => {
            console.error("Error removing item:", error);
            showToast("Network error. Please try again.", true);
        });
    });
}

// Update cart badge
function updateCartBadge() {
    fetch('cart-count.php')
        .then(response => response.json())
        .then(data => {
            document.querySelectorAll('#cart-count, #cart-count-mobile').forEach(badge => {
                badge.textContent = data.cart_quantity || 0;
                badge.style.display = data.cart_quantity > 0 ? 'inline-block' : 'none';
            });
        })
        .catch(error => console.error("Error:", error));
}

// Toast notification
function showToast(message, redirect = false) {
    let existingToast = document.getElementById('toast');
    if (existingToast) existingToast.remove();

    const toast = document.createElement('div');
    toast.id = 'toast';
    toast.innerHTML = `<p>${message}</p>`;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('show');
        if (redirect) {
            setTimeout(() => {
                window.location.href = 'cart.php';
            }, 1500);
        } else {
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    }, 10);
}

// Add to cart form handling
let isSubmitting = false;
document.getElementById('add-to-cart-form')?.addEventListener('submit', function (e) {
    e.preventDefault();
    if (isSubmitting) return;
    isSubmitting = true;

    let hasQuantity = false;
    document.querySelectorAll('#variation-modal input[type="number"]').forEach(input => {
        if (parseInt(input.value) > 0) {
            hasQuantity = true;
        }
    });

    if (!hasQuantity) {
        showToast('Please select at least one variation and quantity.');
        isSubmitting = false;
        return;
    }

    const form = this;
    const formData = new FormData(form);
    const submitButton = form.querySelector('.go-to-cart');
    const originalText = submitButton.innerHTML;

    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    submitButton.disabled = true;

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 10000);

    fetch(form.action, {
        method: 'POST',
        body: formData,
        signal: controller.signal
    })
    .then(response => {
        clearTimeout(timeoutId);
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.status === 'success') {
            document.querySelectorAll('#cart-count, #cart-count-mobile').forEach(badge => {
                badge.textContent = data.cart_quantity || 0;
                badge.style.display = data.cart_quantity > 0 ? 'inline-block' : 'none';
            });
            closeVariationModal();
            showToast('Item(s) added to cart successfully', true);
        } else {
            if (data.cart_quantity > 0) {
                document.querySelectorAll('#cart-count, #cart-count-mobile').forEach(badge => {
                    badge.textContent = data.cart_quantity || 0;
                    badge.style.display = data.cart_quantity > 0 ? 'inline-block' : 'none';
                });
                showToast('Some items added: ' + (data.errors?.join(', ') || data.message), true);
                closeVariationModal();
            } else {
                showToast('Error: ' + (data.message || 'Failed to add items to cart.'));
            }
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        if (error.name === 'AbortError') {
            showToast('Request timed out. Items may have been added. Please check your cart.');
        } else {
            showToast('Network error: ' + error.message + '. Items may have been added.');
        }
        updateCartBadge();
    })
    .finally(() => {
        clearTimeout(timeoutId);
        submitButton.innerHTML = originalText;
        submitButton.disabled = false;
        isSubmitting = false;
    });
});

// Update shipping calculation
function updateShipping() {
    const region = document.getElementById('county').value;
    const town = document.getElementById('town').value;

    if (region && town) {
        fetch('fetch_shipping.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `region=${encodeURIComponent(region)}&town=${encodeURIComponent(town)}`
        })
        .then(response => response.json())
        .then(data => {
            const shippingFee = data.shipping_fee || 0;
            document.getElementById('shipping-cost').textContent = `Ksh ${shippingFee.toFixed(2)}`;
            updateCartTotal();
        })
        .catch(error => {
            console.error('Error fetching shipping:', error);
            document.getElementById('shipping-cost').textContent = 'Ksh 0.00';
            updateCartTotal();
        });
    } else {
        document.getElementById('shipping-cost').textContent = 'Ksh 0.00';
        updateCartTotal();
    }
}

// Update cart total
function updateCartTotal() {
    let subtotal = 0;
    document.querySelectorAll('.item-subtotal').forEach(cell => {
        subtotal += parseFloat(cell.textContent.replace('Ksh', '').replace(/,/g, '').trim()) || 0;
    });

    let shippingCost = parseFloat(document.getElementById('shipping-cost').textContent.replace('Ksh', '').replace(/,/g, '').trim()) || 0;
    let total = subtotal + shippingCost;

    document.getElementById('subtotal-amount').textContent = `Ksh ${subtotal.toFixed(2)}`;
    document.getElementById('total-amount').textContent = `Ksh ${total.toFixed(2)}`;
}

// Check if cart is empty
function checkIfCartIsEmpty() {
    const cartTableBody = document.querySelector('.cart-table tbody');
    if (!cartTableBody || cartTableBody.children.length === 0) {
        document.querySelector('#cart').innerHTML = `
            <div class="empty-cart">
                <h3>🛒 Your Cart is Empty</h3>
                <a href="hop.php" class="btn">Continue Shopping</a>
            </div>
        `;
    }
}
// Fill address form with saved address data
function fillAddressForm(select) {
    const selectedOption = select.options[select.selectedIndex];
    const addressForm = document.getElementById('addressForm');
    if (selectedOption.value) {
        document.getElementById('full_name').value = selectedOption.dataset.fullname;
        document.getElementById('checkout_phone_number').value = selectedOption.dataset.phone;
        document.getElementById('address_line1').value = selectedOption.dataset.address1;
        document.getElementById('address_line2').value = selectedOption.dataset.address2 || '';
        document.getElementById('county').value = selectedOption.dataset.county;
        
        fetch('get_towns.php?region=' + encodeURIComponent(selectedOption.dataset.county))
            .then(response => response.json())
            .then(towns => {
                const townSelect = document.getElementById('town');
                townSelect.innerHTML = '<option value="">Select Town</option>';
                towns.forEach(town => {
                    const option = document.createElement('option');
                    option.value = town;
                    option.textContent = town.replace(/_/g, ' ');
                    if (town === selectedOption.dataset.town) {
                        option.selected = true;
                    }
                    townSelect.appendChild(option);
                });
                updateShipping();
            })
            .catch(error => {
                console.error('Error fetching towns:', error);
                showToast('Error fetching towns');
            });
        
        document.getElementById('postal_code').value = selectedOption.dataset.postal;
        if (document.getElementById('save_address')) {
            document.getElementById('save_address').checked = false;
        }
        if (document.getElementById('is_default')) {
            document.getElementById('is_default').checked = false;
        }
    } else {
        addressForm.reset();
        document.getElementById('town').innerHTML = '<option value="">Select Town</option>';
        updateShipping();
    }
}

// Save address
function saveAddress() {
    const addressForm = document.getElementById('addressForm');
    const formData = new FormData(addressForm);
    formData.append('saved_address_id', document.getElementById('savedAddress')?.value || '');
    
    fetch('save_address.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message);
            if (data.address_id) {
                // Update saved address dropdown if new address was saved
                const savedAddressSelect = document.getElementById('savedAddress');
                if (savedAddressSelect) {
                    const newOption = document.createElement('option');
                    newOption.value = data.address_id;
                    newOption.dataset.fullname = formData.get('full_name');
                    newOption.dataset.phone = formData.get('phone_number');
                    newOption.dataset.address1 = formData.get('address_line1');
                    newOption.dataset.address2 = formData.get('address_line2');
                    newOption.dataset.county = formData.get('county');
                    newOption.dataset.town = formData.get('town');
                    newOption.dataset.postal = formData.get('postal_code');
                    newOption.textContent = `${formData.get('full_name')} - ${formData.get('address_line1')}, ${formData.get('town')}`;
                    savedAddressSelect.appendChild(newOption);
                    savedAddressSelect.value = data.address_id;
                }
            }
            updateShipping();
        } else {
            showToast('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error saving address:', error);
        showToast('Error saving address. Please try again.');
    });
}

// Phone number validation for addressForm
document.getElementById('checkout_phone_number')?.addEventListener('input', function(e) {
    const phoneInput = e.target;
    const errorDiv = document.getElementById('phone-error');
    const phone = phoneInput.value.replace(/\D/g, ''); // Remove all non-digit characters
    
    // Accepted formats: +254704003130, 254704003130, 0704003130, 0104003130
    const isValid = /^(\+?254\d{9}|07\d{8}|01\d{8})$/.test(phoneInput.value) || 
                   /^(254|07|01)\d{8}$/.test(phone);
    
    if (!isValid) {
        errorDiv.textContent = 'Please enter a valid Kenyan number (e.g., 0704003130, 0104003130, or +254704003130)';
        errorDiv.style.display = 'block';
        phoneInput.style.borderColor = 'red';
    } else {
        errorDiv.style.display = 'none';
        phoneInput.style.borderColor = '';
    }
});

// Format phone number on blur
document.getElementById('checkout_phone_number')?.addEventListener('blur', function(e) {
    let phone = e.target.value.replace(/\D/g, '');
    
    // Format based on prefix
    if (phone.startsWith('254') && phone.length === 12) {
        e.target.value = `+${phone}`;
    } else if ((phone.startsWith('07') || phone.startsWith('01')) && phone.length === 10) {
        e.target.value = phone; // Leave as is (0704003130 or 0104003130)
    }
});
// Simplified postal code validation
document.getElementById('postal_code')?.addEventListener('input', function(e) {
    const postalInput = e.target;
    const errorDiv = document.getElementById('postal-error');
    const postal = postalInput.value.replace(/\D/g, ''); // Remove non-digits
    
    // Accept 3-10 digits
    const isValid = /^[0-9]{3,10}$/.test(postal);
    
    if (!isValid) {
        postalInput.setCustomValidity('Please enter a valid postal code (3-10 digits)');
        errorDiv.style.display = 'block';
        postalInput.style.borderColor = 'red';
    } else {
        postalInput.setCustomValidity('');
        errorDiv.style.display = 'none';
        postalInput.style.borderColor = '';
    }
    postalInput.form.reportValidity();
});
// Shared validation function
function validatePhoneNumber(phoneInput, errorElement) {
    const phoneRegex = /^(\+?254\d{9}|07\d{8}|01\d{8})$/;
    const phoneValue = phoneInput.value.replace(/\D/g, '');
    
    if (!phoneRegex.test(phoneValue)) {
        if (errorElement) errorElement.style.display = 'block';
        phoneInput.focus();
        return false;
    } else {
        if (errorElement) errorElement.style.display = 'none';
        return true;
    }
}

// Unified checkout handler
function proceedToCheckout(useModal = false) {
    const form = document.getElementById('addressForm');
    const phoneInput = document.getElementById('checkout_phone_number');
    const phoneError = document.getElementById('phone-error');
    
    // Validate phone number
    if (!validatePhoneNumber(phoneInput, phoneError)) {
        return;
    }
    
    // Validate all required fields
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    // Disable button and show loading
    const btn = document.querySelector('.checkout-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    
    // Submit form via AJAX
    fetch('save_address.php', {
        method: 'POST',
        body: new FormData(form)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (useModal) {
                openPaymentModal();
            } else {
                window.location.href = 'checkout.php';
            }
        } else {
            alert(data.message || 'Failed to save address');
            resetCheckoutButton(btn);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
        resetCheckoutButton(btn);
    });
}

// Modal handling (only needed if using modal approach)
function openPaymentModal() {
    const modalContainer = document.createElement('div');
    modalContainer.id = 'paymentModalContainer';
    modalContainer.style.position = 'fixed';
    modalContainer.style.top = '0';
    modalContainer.style.left = '0';
    modalContainer.style.width = '100%';
    modalContainer.style.height = '100%';
    modalContainer.style.backgroundColor = 'rgba(0,0,0,0.5)';
    modalContainer.style.display = 'flex';
    modalContainer.style.justifyContent = 'center';
    modalContainer.style.alignItems = 'center';
    modalContainer.style.zIndex = '1000';
    
    const iframe = document.createElement('iframe');
    iframe.src = 'checkout.php';
    iframe.style.border = 'none';
    iframe.style.borderRadius = '12px';
    iframe.style.width = '90%';
    iframe.style.maxWidth = '450px';
    iframe.style.height = 'auto';
    iframe.style.minHeight = '500px';
    
    modalContainer.appendChild(iframe);
    document.body.appendChild(modalContainer);
    document.body.style.overflow = 'hidden';
}

window.closePaymentModal = function() {
    const modal = document.getElementById('paymentModalContainer');
    if (modal) {
        modal.remove();
        document.body.style.overflow = 'auto';
    }
};

function resetCheckoutButton(btn) {
    btn.disabled = false;
    btn.innerHTML = 'Proceed to Checkout';
}
// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    updateCartTotal();
    updateCartBadge();
    
    const region = document.getElementById('county');
    const town = document.getElementById('town');
    
    if (region) {
        region.addEventListener('change', () => {
            const regionValue = region.value;
            const townSelect = document.getElementById('town');
            townSelect.innerHTML = '<option value="">Select Town</option>';
            
            if (regionValue) {
                fetch('get_towns.php?region=' + encodeURIComponent(regionValue))
                    .then(response => response.json())
                    .then(towns => {
                        towns.forEach(town => {
                            const option = document.createElement('option');
                            option.value = town;
                            option.textContent = town.replace(/_/g, ' ');
                            townSelect.appendChild(option);
                        });
                        updateShipping();
                    })
                    .catch(error => console.error('Error fetching towns:', error));
            } else {
                updateShipping();
            }
        });
    }
    
    if (town) {
        town.addEventListener('change', updateShipping);
    }
    
    if (region && town && region.value && town.value) {
        updateShipping();
    }
});
// Toggle account menu
function toggleMenu() {
    const menu = document.getElementById('accountMenu');
    const icon = document.getElementById('dropdownIcon');
    if (menu && icon) {
        menu.classList.toggle('active');
        icon.classList.toggle('fa-chevron-down');
        icon.classList.toggle('fa-chevron-up');
    }
}

// Close menu on outside click
window.addEventListener("click", (event) => {
    const menu = document.getElementById("accountMenu");
    const button = document.querySelector(".account-button");
    if (menu && button) {
        if (!menu.contains(event.target) && !button.contains(event.target)) {
            menu.classList.remove("active");
        }
    }
});

// Modal handling
function openModal(modalId) {
    closeAllModals();
    const modal = document.getElementById(`${modalId}Modal`);
    if (modal) {
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('show'), 10);
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(`${modalId}Modal`);
    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => modal.style.display = 'none', 300);
        clearFormErrors(modalId);
    }
}

function closeAllModals() {
    ['signup', 'login'].forEach(modalId => {
        const modal = document.getElementById(`${modalId}Modal`);
        if (modal) {
            modal.classList.remove('show');
            modal.style.display = 'none';
            clearFormErrors(modalId);
        }
    });
}

function switchModal(fromModal, toModal) {
    closeModal(fromModal);
    openModal(toModal);
}

// Close modals on outside click or ESC key press
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                const modalId = modal.id.replace('Modal', '');
                closeModal(modalId);
            }
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeAllModals();
        }
    });
});

// Error handling
function clearFormErrors(modalId) {
    const errorDiv = document.getElementById(`${modalId}Error`);
    if (errorDiv) {
        errorDiv.style.display = 'none';
        errorDiv.innerHTML = '';
    }
    const inputs = document.querySelectorAll(`#${modalId}Modal .input-group`);
    inputs.forEach(group => {
        const error = group.querySelector('.error-message');
        if (error) error.remove();
        group.classList.remove('error');
    });
}

function displayErrors(modalId, errors) {
    const errorDiv = document.getElementById(`${modalId}Error`);
    if (!errorDiv) return;
    
    errorDiv.innerHTML = '';
    errorDiv.style.display = 'none';

    if (errors.general) {
        errorDiv.innerHTML = `<p>${errors.general}</p>`;
        errorDiv.style.display = 'block';
        setTimeout(() => {
            errorDiv.style.display = 'none';
        }, 4000);
    }

    const inputs = document.querySelectorAll(`#${modalId}Modal .input-group`);
    inputs.forEach(group => {
        const input = group.querySelector('input');
        if (!input) return;
        
        const field = input.name;
        if (errors[field]) {
            const errorMsg = document.createElement('span');
            errorMsg.className = 'error-message';
            errorMsg.textContent = errors[field];
            group.appendChild(errorMsg);
            group.classList.add('error');
        }
    });
}

// Client-side validation functions
function validateField(field, value, modalId = null) {
    const errors = {};
    switch (field) {
        case 'Full_Name':
            if (!value || !/^[a-zA-Z]+(\s[a-zA-Z]+)+$/.test(value)) {
                errors[field] = 'Full name must contain at least first and last name';
            } else if (!/^[a-zA-Z\s]{2,50}$/.test(value)) {
                errors[field] = 'Full name must be 2-50 characters, letters only';
            }
            break;
        case 'username':
            if (modalId === 'signup') {
                if (!value || !/^[a-zA-Z0-9_]{3,20}$/.test(value)) {
                    errors[field] = 'Username must be 3-20 characters, alphanumeric';
                }
            } else if (modalId === 'login') {
                if (!value) {
                    errors[field] = 'Username or email is required';
                }
            }
            break;
        case 'email':
            if (!value || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                errors[field] = 'Invalid email address';
            }
            break;
        case 'password':
            if (modalId === 'signup') {
                if (!value || value.length < 8) {
                    errors[field] = 'Password must be at least 8 characters';
                } else if (!/[A-Z]/.test(value)) {
                    errors[field] = 'Password must contain at least one uppercase letter';
                } else if (!/[a-z]/.test(value)) {
                    errors[field] = 'Password must contain at least one lowercase letter';
                } else if (!/\d/.test(value)) {
                    errors[field] = 'Password must contain at least one number';
                } else if (!/[!@#$%^&*(),.?":{}|<>]/.test(value)) {
                    errors[field] = 'Password must contain at least one special character';
                }
            } else if (modalId === 'login') {
                if (!value) {
                    errors[field] = 'Password is required';
                }
            }
            break;
        case 'confirm_password':
            const password = document.getElementById('password')?.value;
            if (!value || value !== password) {
                errors[field] = 'Passwords do not match';
            }
            break;
        case 'phone_number':
            if (!value || !/^\+?\d{10,15}$/.test(value)) {
                errors[field] = 'Invalid phone number';
            }
            break;
    }
    return errors;
}

// Real-time validation with debounce
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function validateInputRealTime(input) {
    try {
        const modal = input.closest('.modal-overlay');
        const modalId = modal ? modal.id.replace('Modal', '') : null;
        const field = input.name;
        const value = input.value.trim();
        const errors = validateField(field, value, modalId);

        const inputGroup = input.closest('.input-group');
        if (!inputGroup) return;
        
        const existingError = inputGroup.querySelector('.error-message');
        if (existingError) existingError.remove();
        inputGroup.classList.remove('error');

        if (errors[field]) {
            const errorMsg = document.createElement('span');
            errorMsg.className = 'error-message';
            errorMsg.textContent = errors[field];
            inputGroup.appendChild(errorMsg);
            inputGroup.classList.add('error');
        }
    } catch (error) {
        console.error('Validation error:', error);
    }
}

// Password strength checker
function checkPasswordStrength(password) {
    const strengthDiv = document.getElementById('passwordStrength');
    if (!strengthDiv) return;
    
    strengthDiv.textContent = '';
    strengthDiv.className = 'password-strength';
    
    if (!password) return;
    
    let strength = 0;
    if (password.length >= 8) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;

    if (strength <= 2) {
        strengthDiv.textContent = 'Weak';
        strengthDiv.classList.add('weak');
    } else if (strength === 3) {
        strengthDiv.textContent = 'Moderate';
        strengthDiv.classList.add('moderate');
    } else {
        strengthDiv.textContent = 'Strong';
        strengthDiv.classList.add('strong');
    }
}

// Social login placeholder
function handleSocialLogin(provider) {
    showToast(`Social login with ${provider} is not yet implemented. Please use email signup.`);
}

// Form submission handlers
document.addEventListener('DOMContentLoaded', () => {
    // Signup form submission
    const signupForm = document.getElementById('signupForm');
    if (signupForm) {
        signupForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            const errorDiv = document.getElementById('signupError');
            const submitButton = form.querySelector('.submit-btn');

            // Clear previous errors
            if (errorDiv) {
                errorDiv.innerHTML = '';
                errorDiv.style.display = 'none';
            }
            
            const errorFields = form.querySelectorAll('.error');
            errorFields.forEach(field => {
                field.innerHTML = '';
                field.style.display = 'none';
            });

            // Show loading state
            const originalBtnText = submitButton.innerHTML;
            submitButton.innerHTML = '<span class="spinner"></span> Sending...';
            submitButton.disabled = true;

            try {
                const response = await fetch('signup.php', {
                    method: 'POST',
                    body: formData,
                });
                const data = await response.json();

                if (data.status === 'success') {
                    closeModal('signup');
                    showToast('Signup successful! Redirecting to verification...');
                    
                    // FIX: Redirect to verification page after delay
                    setTimeout(() => {
                        window.location.href = 'verify-code.php';
                    }, 2000);
                } else {
                    // Display errors
                    if (data.message) {
                        if (errorDiv) {
                            errorDiv.innerHTML = `<p>${data.message}</p>`;
                            errorDiv.style.display = 'block';
                        }
                    }
                    if (data.errors) {
                        displayErrors('signup', data.errors);
                    }
                }
            } catch (error) {
                if (errorDiv) {
                    errorDiv.innerHTML = '<p>Connection error. Please try again.</p>';
                    errorDiv.style.display = 'block';
                }
            } finally {
                // Restore button state
                submitButton.innerHTML = originalBtnText;
                submitButton.disabled = false;
            }
        });
    }

    // Login form submission
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (isSubmitting) return;
            isSubmitting = true;

            const form = e.target;
            const formData = new FormData(form);
            const errorDiv = document.getElementById('loginError');
            const submitButton = form.querySelector('.submit-btn');
            
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Logging in...';
            }

            const modalId = 'login';
            const errors = {};
            for (const [field, value] of formData.entries()) {
                Object.assign(errors, validateField(field, value, modalId));
            }

            clearFormErrors('login');
            if (Object.keys(errors).length > 0) {
                displayErrors('login', errors);
                isSubmitting = false;
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Login';
                }
                return;
            }

            try {
                const response = await fetch('login.php', {
                    method: 'POST',
                    body: formData,
                });
                const data = await response.json();

                if (data.status === 'success') {
                    closeModal('login');
                    showToast('Login successful! Redirecting...');
                    setTimeout(() => window.location.href = 'hop.php', 2000);
                } else {
                    displayErrors('login', data.errors || { general: data.message });
                }
            } catch (error) {
                if (errorDiv) {
                    errorDiv.innerHTML = '<p>Connection error. Please try again.</p>';
                    errorDiv.style.display = 'block';
                }
            } finally {
                isSubmitting = false;
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Login';
                }
            }
        });
    }

    // Real-time input validation
    document.querySelectorAll('#signupModal input, #loginModal input').forEach(input => {
        input.addEventListener('input', debounce((e) => validateInputRealTime(e.target), 300));
    });

    // Password toggle - FIXED VERSION
    document.addEventListener('click', function(e) {
        const toggle = e.target.closest('.password-toggle');
        if (toggle) {
            const targetId = toggle.dataset.target;
            const input = document.getElementById(targetId);
            if (input) {
                const type = input.type === 'password' ? 'text' : 'password';
                input.type = type;
                
                // Toggle icon classes on the <i> element inside the toggle
                const icon = toggle.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-eye');
                    icon.classList.toggle('fa-eye-slash');
                }
            }
        }
    });

    // Password strength real-time
    const passwordInput = document.getElementById('password');
    if (passwordInput) {
        passwordInput.addEventListener('input', (e) => {
            checkPasswordStrength(e.target.value);
        });
    }
});

function resendVerificationEmail() {
    fetch('resend-verification.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
    })
    .then(response => response.json())
    .then(data => {
        showToast(data.message);
    })
    .catch(error => {
        showToast('Error resending email');
    });
}

// Add this to your existing modal open/close functions
function openVariationModal() {
    document.getElementById('variation-modal').style.display = 'flex';
    document.body.classList.add('modal-open'); // Add this line
}

function closeVariationModal() {
    document.getElementById('variation-modal').style.display = 'none';
    document.body.classList.remove('modal-open'); // Add this line
}

// Also add for size guide modal if needed
function openSizeGuideModal() {
    document.getElementById('size-guide-modal').style.display = 'flex';
    document.body.classList.add('modal-open');
}

function closeSizeGuideModal() {
    document.getElementById('size-guide-modal').style.display = 'none';
    document.body.classList.remove('modal-open');
}
// Variation selection functions
function selectVariation(type, value) {
    // Implementation
}

function adjustQuantity(type, value, change, max) {
    // Implementation
}

function updateStickyPrice() {
    // Implementation
}
//contact page
document.addEventListener('DOMContentLoaded', function() {
      updateCartCount();
      setupTabs();
      setupFAQ();
      setupChat();
      setupMap();
      setupChatButton();
    });

    function updateCartCount() {
      let cartCount = localStorage.getItem('cartCount') || 0;
      document.getElementById('cart-count').textContent = cartCount;
    }

    function setupTabs() {
      const tabButtons = document.querySelectorAll('.tab-btn');
      const tabContents = document.querySelectorAll('.tab-content');
      const sidebarLinks = document.querySelectorAll('.help-sidebar a');

      function switchTab(tabId) {
        tabContents.forEach(content => content.classList.remove('active'));
        tabButtons.forEach(button => button.classList.remove('active'));
        document.getElementById(`${tabId}-tab`).classList.add('active');
        document.querySelector(`.tab-btn[data-tab="${tabId}"]`).classList.add('active');
      }

      tabButtons.forEach(button => {
        button.addEventListener('click', () => {
          switchTab(button.getAttribute('data-tab'));
        });
      });

      sidebarLinks.forEach(link => {
        link.addEventListener('click', (e) => {
          e.preventDefault();
          switchTab(link.getAttribute('data-tab'));
        });
      });
    }

    function setupFAQ() {
      const faqQuestions = document.querySelectorAll('.faq-question');
      faqQuestions.forEach(question => {
        question.addEventListener('click', () => {
          question.parentElement.classList.toggle('active');
        });
      });
    }

    // ... (previous functions: updateCartCount, setupTabs, setupFAQ, etc.)


    let chatSocket;
    let adminOnline = false;
    let chatMessages = document.getElementById('chat-messages');
    let chatInput = document.getElementById('chat-input');
    let sendButton = document.getElementById('send-btn');
    let chatStatusDot = document.getElementById('chat-status-dot');
    let statusIndicator = document.getElementById('status-indicator');
    let adminStatusDot = document.getElementById('admin-status-dot');
    let adminStatusText = document.getElementById('admin-status-text');
    let chatNotice = document.getElementById('chat-notice');
    let userId = 'user_' + Math.random().toString(36).substr(2, 9);
    let reconnectAttempts = 0;

    function setupChat() {
      updateAdminStatusUI(false);
      chatNotice.textContent = "Connecting to server...";
      chatNotice.className = "status-notification status-connecting";
      connectWebSocket();
    }

    function connectWebSocket() {
      try {
        const wsUrl = 'ws://localhost:8080';
        console.log('Connecting to WebSocket at:', wsUrl);
        
        chatSocket = new WebSocket(wsUrl);
        
        chatSocket.onopen = function() {
          console.log('Chat connection established');
          reconnectAttempts = 0;
          chatNotice.textContent = "Checking admin availability...";
          chatNotice.className = "status-notification status-connecting";
          
          try {
            chatSocket.send(JSON.stringify({
              type: 'set_user_type',
              userType: 'user',
              userId: userId
            }));
            
            // Request admin status immediately
            requestAdminStatus();
          } catch (e) {
            console.error('Error sending user type:', e);
          }
        };
        
        chatSocket.onmessage = function(event) {
          console.log('Received raw message:', event.data);
          
          try {
            let message = event.data;
            let jsonStart = message.indexOf('{');
            
            if (jsonStart === -1) {
              console.error('No JSON found in message:', message);
              return;
            }
            
            let jsonString = message.substring(jsonStart);
            const data = JSON.parse(jsonString);
            console.log('Parsed message:', data);
            
            if (data.type === 'status') {
              console.log('Received status update:', data.status);
              adminOnline = data.status === 'online';
              updateAdminStatusUI(adminOnline);
              
              if (adminOnline) {
                chatNotice.textContent = "Admin is online. You can start chatting now!";
                chatNotice.className = "status-notification status-online";
              } else {
                chatNotice.textContent = "Admin is offline. Please leave a message and we'll get back to you soon.";
                chatNotice.className = "status-notification status-offline";
              }
            } 
            else if (data.type === 'message' && data.sender === 'admin') {
              addMessage(data.content, 'support');
            }
            else if (data.type === 'admin_connected') {
              adminOnline = true;
              updateAdminStatusUI(true);
              chatNotice.textContent = "Admin is online. You can start chatting now!";
              chatNotice.className = "status-notification status-online";
            }
            else if (data.type === 'admin_disconnected') {
              adminOnline = false;
              updateAdminStatusUI(false);
              chatNotice.textContent = "Admin is offline. Please leave a message and we'll get back to you soon.";
              chatNotice.className = "status-notification status-offline";
            }
            else if (data.type === 'user_registered') {
              // Confirmation that user is registered with server
              console.log('User registered successfully');
            }
          } catch (e) {
            console.error('Error parsing message:', e, event.data);
          }
        };
        
        chatSocket.onclose = function(event) {
          console.log('Chat connection closed', event);
          updateAdminStatusUI(false);
          chatNotice.textContent = "I'll be back soon...";
          chatNotice.className = "status-notification status-offline";
          
          const delay = Math.min(1000 * Math.pow(2, reconnectAttempts), 30000);
          reconnectAttempts++;
          setTimeout(connectWebSocket, delay);
        };
        
        chatSocket.onerror = function(error) {
          console.error('WebSocket error:', error);
          updateAdminStatusUI(false);
          chatNotice.textContent = "Connection error. Please try again later.";
          chatNotice.className = "status-notification status-offline";
        };
        
      } catch (e) {
        console.error('WebSocket connection failed:', e);
        updateAdminStatusUI(false);
        chatNotice.textContent = "Connection failed. Please check your network.";
        chatNotice.className = "status-notification status-offline";
        setTimeout(connectWebSocket, 5000);
      }
      
      // Send message function
      function sendMessage() {
        const message = chatInput.value.trim();
        if (message) {
          // Add user message to UI immediately
          addMessage(message, 'user');
          
          try {
            if (chatSocket.readyState === WebSocket.OPEN) {
              // Send message with user ID
              chatSocket.send(JSON.stringify({
                type: 'message',
                content: message,
                sender: 'user',
                userId: userId
              }));
            } else {
              addMessage("Connection lost. Your message was not sent.", 'support');
            }
          } catch (e) {
            console.error('Error sending message:', e);
          }
          
          chatInput.value = '';
          
          if (!adminOnline) {
            setTimeout(() => {
              addMessage("Thank you for your message. Our support team is currently offline. We'll respond as soon as possible.", 'support');
            }, 1000);
          }
        }
      }
      
      sendButton.addEventListener('click', sendMessage);
      chatInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
          sendMessage();
        }
      });
    }

    function requestAdminStatus() {
      try {
        if (chatSocket.readyState === WebSocket.OPEN) {
          chatSocket.send(JSON.stringify({
            type: 'get_admin_status'
          }));
        }
      } catch (e) {
        console.error('Error requesting admin status:', e);
      }
    }

    function addMessage(text, sender) {
      const messageElement = document.createElement('div');
      messageElement.className = `message ${sender}`;
      
      const now = new Date();
      const timeString = `${now.getHours()}:${now.getMinutes().toString().padStart(2, '0')}`;
      
      messageElement.innerHTML = `
        <div class="message-content">${text}</div>
        <div class="message-time">${timeString}</div>
      `;
      
      messageElement.style.animation = 'fadeIn 0.3s ease-in';
      chatMessages.appendChild(messageElement);
      chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function updateAdminStatusUI(isOnline) {
      adminOnline = isOnline;
      
      if (isOnline) {
        chatStatusDot.style.background = '#4caf50';
        statusIndicator.textContent = 'Online';
        adminStatusDot.style.background = '#4caf50';
        adminStatusText.textContent = 'Online';
      } else {
        chatStatusDot.style.background = '#f44336';
        statusIndicator.textContent = 'Offline';
        adminStatusDot.style.background = '#f44336';
        adminStatusText.textContent = 'Offline';
      }
    }

function setupMap() {
  // Create map centered on your location
  const map = L.map('branch-map', {
    zoomControl: false, // Disable default zoom controls
    scrollWheelZoom: true,
    touchZoom: true,
    doubleClickZoom: true
  }).setView([-1.1829248, 36.9000448], 15);

  // Add tile layer with a modern style
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    detectRetina: true
  }).addTo(map);

  // Custom zoom controls
  const zoomIn = document.querySelector('.zoom-in');
  const zoomOut = document.querySelector('.zoom-out');
  
  zoomIn.addEventListener('click', () => map.zoomIn());
  zoomOut.addEventListener('click', () => map.zoomOut());

  // Get Directions button functionality
  document.querySelector('.get-directions-btn').addEventListener('click', () => {
    const url = `https://www.google.com/maps/dir/?api=1&destination`;
    window.open(url, '_blank');
  });

  // Optional: Add a subtle circle to indicate area (not a marker)
  L.circle([-1.1829248, 36.9000448], {
    color: '#088178',
    fillColor: '#088178',
    fillOpacity: 0.2,
    radius: 100
  }).addTo(map);
}
    

    function setupChatButton() {
      const chatButton = document.querySelector('.chat-button');
      chatButton.addEventListener('click', () => {
        // Switch to chat tab
        const chatTab = document.querySelector('.tab-btn[data-tab="chat"]');
        chatTab.click();
        
        // Scroll to chat section
        document.querySelector('.help-content').scrollIntoView({ behavior: 'smooth' });
        
        // Add animation to button
        chatButton.style.animation = 'pulse 1s';
        setTimeout(() => {
          chatButton.style.animation = '';
        }, 1000);
      });
    }



    