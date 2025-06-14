// Global Variables
let currentUser = null;
let userPoints = 0;
let shopItems = [
    { id: 1, name: 'Diamond Sword', price: 100, command: 'give {player} diamond_sword 1' },
    { id: 2, name: 'Iron Armor Set', price: 200, command: 'give {player} iron_helmet 1; give {player} iron_chestplate 1; give {player} iron_leggings 1; give {player} iron_boots 1' },
    { id: 3, name: 'Enchanted Book', price: 150, command: 'give {player} enchanted_book 1' },
    { id: 4, name: 'Golden Apple', price: 50, command: 'give {player} golden_apple 5' },
    { id: 5, name: 'Elytra', price: 500, command: 'give {player} elytra 1' }
];
let selectedItem = null;

// DOM Elements
const loginSection = document.getElementById('loginSection');
const shopSection = document.getElementById('shopSection');
const userInfo = document.getElementById('userInfo');
const loginForm = document.getElementById('loginForm');
const addItemForm = document.getElementById('addItemForm');
const itemsGrid = document.getElementById('itemsGrid');
const purchaseModal = document.getElementById('purchaseModal');
const pointsModal = document.getElementById('pointsModal');

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    loadShopItems();
    checkUserSession();
});

loginForm.addEventListener('submit', function(e) {
    e.preventDefault();
    login();
});

addItemForm.addEventListener('submit', function(e) {
    e.preventDefault();
    addNewItem();
});

// User Authentication
function login() {
    const username = document.getElementById('username').value.trim();
    
    if (!username) {
        showMessage('กรุณาใส่ชื่อผู้เล่น', 'error');
        return;
    }

    // Validate Minecraft username (basic validation)
    if (username.length < 3 || username.length > 16) {
        showMessage('ชื่อผู้เล่นต้องมีความยาว 3-16 ตัวอักษร', 'error');
        return;
    }

    if (!/^[a-zA-Z0-9_]+$/.test(username)) {
        showMessage('ชื่อผู้เล่นสามารถมีได้เฉพาะตัวอักษร ตัวเลข และ _', 'error');
        return;
    }

    currentUser = username;
    userPoints = getUserPoints(username);
    
    showShop();
    showMessage(`ยินดีต้อนรับ ${username}!`, 'success');
}

function logout() {
    currentUser = null;
    userPoints = 0;
    showLogin();
    showMessage('ออกจากระบบเรียบร้อย', 'success');
}

function showLogin() {
    loginSection.style.display = 'block';
    shopSection.style.display = 'none';
    userInfo.style.display = 'none';
}

function showShop() {
    loginSection.style.display = 'none';
    shopSection.style.display = 'block';
    userInfo.style.display = 'flex';
    
    document.getElementById('userDisplay').textContent = currentUser;
    updatePointsDisplay();
}

// Points System
function getUserPoints(username) {
    const saved = localStorage.getItem(`points_${username}`);
    return saved ? parseInt(saved) : 0;
}

function saveUserPoints(username, points) {
    localStorage.setItem(`points_${username}`, points.toString());
}

function updatePointsDisplay() {
    document.getElementById('userPoints').textContent = userPoints;
    document.getElementById('pointsDisplay').textContent = userPoints;
}

function addPoints() {
    pointsModal.style.display = 'block';
}

function addPointsAmount(amount) {
    userPoints += amount;
    saveUserPoints(currentUser, userPoints);
    updatePointsDisplay();
    closePointsModal();
    showMessage(`เติมพ้อย ${amount} สำเร็จ!`, 'success');
}

function closePointsModal() {
    pointsModal.style.display = 'none';
}

// Shop Items Management
function loadShopItems() {
    const saved = localStorage.getItem('shopItems');
    if (saved) {
        shopItems = JSON.parse(saved);
    }
    renderShopItems();
}

function saveShopItems() {
    localStorage.setItem('shopItems', JSON.stringify(shopItems));
}

function renderShopItems() {
    itemsGrid.innerHTML = '';
    
    shopItems.forEach(item => {
        const itemCard = document.createElement('div');
        itemCard.className = 'item-card';
        itemCard.innerHTML = `
            <h4><i class="fas fa-gem"></i> ${item.name}</h4>
            <div class="item-price">${item.price} พ้อย</div>
            <button class="buy-btn" onclick="purchaseItem(${item.id})" ${userPoints < item.price ? 'disabled' : ''}>
                <i class="fas fa-shopping-cart"></i> ซื้อ
            </button>
        `;
        itemsGrid.appendChild(itemCard);
    });
}

function addNewItem() {
    const name = document.getElementById('itemName').value.trim();
    const price = parseInt(document.getElementById('itemPrice').value);
    const command = document.getElementById('itemCommand').value.trim();

    if (!name || !price || !command) {
        showMessage('กรุณากรอกข้อมูลให้ครบถ้วน', 'error');
        return;
    }

    if (price <= 0) {
        showMessage('ราคาต้องมากกว่า 0', 'error');
        return;
    }

    const newItem = {
        id: Date.now(),
        name: name,
        price: price,
        command: command
    };

    shopItems.push(newItem);
    saveShopItems();
    renderShopItems();
    
    // Clear form
    document.getElementById('itemName').value = '';
    document.getElementById('itemPrice').value = '';
    document.getElementById('itemCommand').value = '';
    
    showMessage(`เพิ่มสินค้า "${name}" สำเร็จ!`, 'success');
}

// Purchase System
function purchaseItem(itemId) {
    if (!currentUser) {
        showMessage('กรุณาเข้าสู่ระบบก่อน', 'error');
        return;
    }

    const item = shopItems.find(i => i.id === itemId);
    if (!item) {
        showMessage('ไม่พบสินค้านี้', 'error');
        return;
    }

    if (userPoints < item.price) {
        showMessage('พ้อยไม่เพียงพอ', 'error');
        return;
    }

    selectedItem = item;
    showPurchaseModal(item);
}

function showPurchaseModal(item) {
    const details = document.getElementById('purchaseDetails');
    details.innerHTML = `
        <div style="text-align: center;">
            <h4><i class="fas fa-gem"></i> ${item.name}</h4>
            <p><strong>ราคา:</strong> ${item.price} พ้อย</p>
            <p><strong>พ้อยคงเหลือหลังซื้อ:</strong> ${userPoints - item.price} พ้อย</p>
            <p><strong>คำสั่ง:</strong> <code>${item.command.replace('{player}', currentUser)}</code></p>
        </div>
    `;
    purchaseModal.style.display = 'block';
}

function confirmPurchase() {
    if (!selectedItem || !currentUser) return;

    // Deduct points
    userPoints -= selectedItem.price;
    saveUserPoints(currentUser, userPoints);
    updatePointsDisplay();

    // Execute RCON command (simulated)
    executeRCONCommand(selectedItem.command, currentUser);

    // Log purchase
    logPurchase(currentUser, selectedItem);

    closePurchaseModal();
    renderShopItems(); // Update buy buttons
    showMessage(`ซื้อ ${selectedItem.name} สำเร็จ! ไอเทมจะถูกส่งให้ในเกม`, 'success');
    
    selectedItem = null;
}

function closePurchaseModal() {
    purchaseModal.style.display = 'none';
    selectedItem = null;
}

// RCON System (Simulated)
function executeRCONCommand(command, player) {
    const finalCommand = command.replace(/{player}/g, player);
    
    // In a real implementation, this would send the command to your Minecraft server via RCON
    console.log(`Executing RCON command: ${finalCommand}`);
    
    // Simulate RCON execution
    fetch('rcon.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            command: finalCommand,
            player: player
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('RCON command executed successfully');
        } else {
            console.error('RCON command failed:', data.error);
            showMessage('เกิดข้อผิดพลาดในการส่งไอเทม กรุณาติดต่อแอดมิน', 'error');
        }
    })
    .catch(error => {
        console.error('RCON error:', error);
        // In demo mode, we'll just show success
        showMessage('คำสั่งถูกส่งไปยังเซิร์ฟเวอร์แล้ว (โหมดทดสอบ)', 'success');
    });
}

// Purchase Logging
function logPurchase(player, item) {
    const purchases = JSON.parse(localStorage.getItem('purchases') || '[]');
    purchases.push({
        player: player,
        item: item.name,
        price: item.price,
        timestamp: new Date().toISOString(),
        command: item.command
    });
    localStorage.setItem('purchases', JSON.stringify(purchases));
}

// Utility Functions
function showMessage(message, type) {
    // Remove existing messages
    const existingMessages = document.querySelectorAll('.message');
    existingMessages.forEach(msg => msg.remove());

    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${type}`;
    messageDiv.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
    
    document.querySelector('.container').insertBefore(messageDiv, document.querySelector('.container').firstChild);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        messageDiv.remove();
    }, 5000);
}

function checkUserSession() {
    // Check if user was previously logged in (optional feature)
    const savedUser = localStorage.getItem('currentUser');
    if (savedUser) {
        currentUser = savedUser;
        userPoints = getUserPoints(savedUser);
        showShop();
    }
}

// Save current user session
function saveUserSession() {
    if (currentUser) {
        localStorage.setItem('currentUser', currentUser);
    } else {
        localStorage.removeItem('currentUser');
    }
}

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target === purchaseModal) {
        closePurchaseModal();
    }
    if (event.target === pointsModal) {
        closePointsModal();
    }
}

// Update points display when shop items are rendered
setInterval(() => {
    if (currentUser) {
        renderShopItems();
    }
}, 5000); // Update every 5 seconds

