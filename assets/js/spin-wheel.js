/**
 * Spin Wheel - Free (1/day), Pay $1 per spin, or Pay $5 per spin (different prize pool).
 * API returns reward_index so the wheel always lands on the correct segment.
 */
(function() {
    var container = document.getElementById('spinWheelModal');
    if (!container) return;

    var wheelSegments = document.getElementById('spinWheelSegments');
    var spinBtn = document.getElementById('spinWheelSpinBtn');
    var resultBox = document.getElementById('spinResultBox');
    var resultValue = document.getElementById('spinResultValue');
    var closeModal = document.getElementById('spinWheelCloseBtn');
    var modalTitle = container.querySelector('.ud-modal-header h3');

    var rewards = [];
    try {
        var scriptTag = document.querySelector('script[data-spin-rewards]');
        if (scriptTag && scriptTag.getAttribute('data-spin-rewards')) {
            rewards = JSON.parse(scriptTag.getAttribute('data-spin-rewards'));
        }
    } catch (e) {}
    if (!rewards.length) rewards = [
        { label: '$0.50' }, { label: '$1.00' }, { label: '$2.00' }, { label: '$0.25' },
        { label: '$5.00' }, { label: 'Bonus Spin' }, { label: '$1.50' }, { label: '$0.75' }
    ];

    var rewards5 = [];
    try {
        var script5Tag = document.querySelector('script[data-spin-rewards-5]');
        if (script5Tag && script5Tag.getAttribute('data-spin-rewards-5')) {
            rewards5 = JSON.parse(script5Tag.getAttribute('data-spin-rewards-5'));
        }
    } catch (e) {}
    if (!rewards5.length) rewards5 = [
        { label: '$100' }, { label: '$50' }, { label: '$10' }, { label: '$7' }, { label: '$0' }, { label: '$5' }
    ];

    var segmentAngle = 360 / rewards.length;
    var currentRotation = 0;
    var isSpinning = false;
    var spinMode = 'free'; // 'free' | 'paid' | 'paid5'
    var lastReward = null; // set when spin completes, cleared when modal closes
    var premiumColors = ['#6366f1', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#06b6d4', '#3b82f6', '#eab308'];

    function buildWheel() {
        if (!wheelSegments) return;
        var gradientStops = rewards.map(function(r, i) {
            return premiumColors[i % premiumColors.length] + ' ' + (i * segmentAngle) + 'deg ' + ((i + 1) * segmentAngle) + 'deg';
        }).join(', ');
        wheelSegments.style.background = 'conic-gradient(' + gradientStops + ')';
        wheelSegments.innerHTML = '';
        /* Conic-gradient: 0deg = top, angles increase clockwise. Position labels to match. */
        rewards.forEach(function(r, i) {
            var angleDeg = i * segmentAngle + segmentAngle / 2;
            var angleRad = angleDeg * Math.PI / 180;
            var radius = 42;
            var x = 50 + radius * Math.sin(angleRad);
            var y = 50 - radius * Math.cos(angleRad);
            var span = document.createElement('span');
            span.className = 'spin-segment-label';
            span.style.cssText = 'position:absolute;left:' + x + '%;top:' + y + '%;transform:translate(-50%,-50%) rotate(' + angleDeg + 'deg);font-size:10px;font-weight:800;color:#fff;text-shadow:0 1px 4px rgba(0,0,0,0.8);white-space:nowrap;pointer-events:none;';
            span.textContent = r.label || '';
            wheelSegments.appendChild(span);
        });
    }

    var defaultRewards1 = [
        { label: '$0.50' }, { label: '$1.00' }, { label: '$2.00' }, { label: '$0.25' },
        { label: '$5.00' }, { label: 'Bonus Spin' }, { label: '$1.50' }, { label: '$0.75' }
    ];
    function openSpinModal(mode) {
        if (!container) return;
        spinMode = mode || 'free';
        if (spinMode === 'paid5') {
            rewards = rewards5.length ? rewards5.slice() : [{ label: '$100' }, { label: '$50' }, { label: '$10' }, { label: '$7' }, { label: '$0' }, { label: '$5' }];
        } else {
            try {
                var tag = document.querySelector('script[data-spin-rewards]');
                rewards = (tag && tag.getAttribute('data-spin-rewards')) ? JSON.parse(tag.getAttribute('data-spin-rewards')) : defaultRewards1.slice();
            } catch (e) {
                rewards = defaultRewards1.slice();
            }
            if (!rewards.length) rewards = defaultRewards1.slice();
        }
        segmentAngle = 360 / rewards.length;
        container.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        if (resultBox) {
            resultBox.style.display = 'none';
            resultBox.classList.remove('spin-result-show');
        }
        var spinLabel = spinMode === 'paid5' ? 'Spin for $5' : (spinMode === 'paid' ? 'Spin for $1' : 'Free Spin');
        if (spinBtn) {
            spinBtn.disabled = false;
            spinBtn.onclick = null;
            spinBtn.setAttribute('data-action', 'spin');
            spinBtn.innerHTML = '<i class="fas fa-sync-alt"></i> ' + spinLabel;
        }
        if (modalTitle) {
            var title = spinMode === 'paid5' ? '<i class="fas fa-coins"></i> Spin for $5' : (spinMode === 'paid' ? '<i class="fas fa-coins"></i> Spin for $1' : '<i class="fas fa-gift"></i> Daily Free Spin');
            modalTitle.innerHTML = title;
        }
        buildWheel();
    }

    function closeSpinModal() {
        if (container) container.style.display = 'none';
        document.body.style.overflow = '';
        if (lastReward != null && typeof window.onSpinComplete === 'function') {
            window.onSpinComplete(lastReward);
        }
        lastReward = null;
    }

    function spinToIndex(index) {
        if (!wheelSegments || isSpinning) return;
        isSpinning = true;
        if (spinBtn) spinBtn.disabled = true;

        index = Math.max(0, Math.min(index, rewards.length - 1));
        /* Pointer at top. We need final rotation R (mod 360) = 360 - segmentCenter so that
           segment center is at the top. Add 6 full turns + delta from current position. */
        var segmentCenterAngle = index * segmentAngle + segmentAngle / 2;
        var currentMod = ((currentRotation % 360) + 360) % 360;
        var targetMod = (360 - segmentCenterAngle + 360) % 360;
        var delta = (targetMod - currentMod + 360) % 360;
        if (delta < 90) delta += 360;
        var targetAngle = 360 * 6 + delta;
        currentRotation += targetAngle;
        wheelSegments.style.transition = 'transform 5s cubic-bezier(0.17, 0.67, 0.12, 0.99)';
        wheelSegments.style.transform = 'rotate(' + currentRotation + 'deg)';

        setTimeout(function() {
            isSpinning = false;
            var reward = rewards[index];
            var displayLabel = (reward && reward.label) ? reward.label : 'Bonus';
            if (resultValue) resultValue.textContent = displayLabel;
            if (resultBox) {
                resultBox.style.display = 'block';
                resultBox.classList.add('spin-result-show');
            }
            lastReward = reward;
            if (spinBtn) {
                spinBtn.disabled = false;
                spinBtn.onclick = null;
                var again = (spinMode === 'paid' || spinMode === 'paid5');
                spinBtn.setAttribute('data-action', again ? 'spin-again' : 'close');
                spinBtn.innerHTML = spinMode === 'paid5'
                    ? '<i class="fas fa-sync-alt"></i> Spin again for $5'
                    : (spinMode === 'paid' ? '<i class="fas fa-sync-alt"></i> Spin again for $1' : '<i class="fas fa-times"></i> Close');
            }
        }, 5200);
    }

    function doSpin() {
        if (isSpinning) return;
        spinBtn.disabled = true;
        spinBtn.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Spinning...';

        var body = 'spin=1';
        if (spinMode === 'paid') body += '&paid=1&cost=1';
        if (spinMode === 'paid5') body += '&paid=1&cost=5';

        var spinApiUrl = (typeof window.SPIN_API_BASE === 'string' && window.SPIN_API_BASE) ? window.SPIN_API_BASE + 'api/spin.php' : 'api/spin.php';
        fetch(spinApiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: body,
            credentials: 'same-origin'
        })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.reward && typeof data.reward_index === 'number') {
                    spinToIndex(data.reward_index);
                    if (data.new_balance != null && typeof window.updateDashboardBalance === 'function') {
                        window.updateDashboardBalance(data.new_balance);
                    }
                } else {
                    alert(data.message || (spinMode === 'paid' || spinMode === 'paid5' ? 'Insufficient balance.' : 'You already used your free spin today.'));
                    spinBtn.disabled = false;
                    spinBtn.setAttribute('data-action', 'spin');
                    spinBtn.innerHTML = spinMode === 'paid5' ? '<i class="fas fa-sync-alt"></i> Spin for $5' : (spinMode === 'paid' ? '<i class="fas fa-sync-alt"></i> Spin for $1' : '<i class="fas fa-sync-alt"></i> Free Spin');
                }
            })
            .catch(function() {
                alert('Something went wrong. Please try again.');
                spinBtn.disabled = false;
                spinBtn.setAttribute('data-action', 'spin');
                spinBtn.innerHTML = spinMode === 'paid5' ? '<i class="fas fa-sync-alt"></i> Spin for $5' : (spinMode === 'paid' ? '<i class="fas fa-sync-alt"></i> Spin for $1' : '<i class="fas fa-sync-alt"></i> Free Spin');
            });
    }

    buildWheel();

    function bindSpinButtons() {
        var openFreeBtn = document.getElementById('openSpinWheelBtn');
        var openPaid5Btn = document.getElementById('openSpinWheelPaid5Btn');
        if (openFreeBtn && !openFreeBtn.dataset.spinBound) {
            openFreeBtn.dataset.spinBound = '1';
            openFreeBtn.addEventListener('click', function(e) { e.preventDefault(); openSpinModal('free'); });
        }
        if (openPaid5Btn && !openPaid5Btn.dataset.spinBound) {
            openPaid5Btn.dataset.spinBound = '1';
            openPaid5Btn.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation(); openSpinModal('paid5'); });
        }
    }
    bindSpinButtons();
    document.addEventListener('DOMContentLoaded', bindSpinButtons);
    var spinCard = document.querySelector('.ud-spin-card');
    if (spinCard) {
        spinCard.addEventListener('click', function(e) {
            var t = e.target;
            while (t && t !== spinCard) {
                if (t.getAttribute && t.getAttribute('data-spin-cost') === '5' && !t.disabled) {
                    e.preventDefault();
                    e.stopPropagation();
                    openSpinModal('paid5');
                    return;
                }
                t = t.parentNode;
            }
        });
    }
    if (spinBtn && !spinBtn.dataset.bound) {
        spinBtn.dataset.bound = '1';
        spinBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var action = spinBtn.getAttribute('data-action') || 'spin';
            if (action === 'close') {
                closeSpinModal();
                return;
            }
            if (action === 'spin-again') {
                if (resultBox) {
                    resultBox.style.display = 'none';
                    resultBox.classList.remove('spin-result-show');
                }
                lastReward = null;
                spinBtn.setAttribute('data-action', 'spin');
            }
            doSpin();
        });
    }
    if (closeModal) closeModal.addEventListener('click', closeSpinModal);
    container.addEventListener('click', function(e) {
        if (e.target === container) closeSpinModal();
    });

    window.openSpinWheelModal = openSpinModal;
})();
