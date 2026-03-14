<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/auth.php';

// Handle booking form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_service'])) {
    $service_id = (int)$_POST['service_id'];
    $booking_date = $_POST['booking_date'];
    $booking_time = $_POST['booking_time'];
    $phone = trim($_POST['phone']);
    $notes = trim($_POST['notes'] ?? '');
    
    $errors = [];
    if (empty($service_id)) $errors[] = 'Please select a service';
    if (empty($booking_date)) $errors[] = 'Please select a date';
    if (empty($phone) || !preg_match('/^0[17]\d{8}$/', $phone)) {
        $errors[] = 'Please enter a valid phone number';
    }
    
    if (empty($errors)) {
        $service = fetchOne("SELECT * FROM services WHERE id = ?", [$service_id]);
        if ($service) {
            $booking_id = db_insert('service_bookings', [
                'user_id' => $_SESSION['user_id'],
                'service_id' => $service_id,
                'booking_date' => $booking_date,
                'booking_time' => $booking_time ?: '10:00:00',
                'notes' => $notes,
                'status' => 'confirmed'
            ]);
            
            if ($booking_id) {
                header("Location: profile.php?booking_success=1");
                exit;
            } else {
                $errors[] = 'Failed to create booking. Please try again.';
            }
        } else {
            $errors[] = 'Service not found';
        }
    }
}

$page_title = 'Services';
require_once __DIR__ . '/../templates/header.php';

// Fetch visible services
$services = fetchAll(
    "SELECT id, name, slug, price, description, duration_minutes 
     FROM services 
     WHERE visible = 1
     ORDER BY name ASC"
);

// Generate available time slots
$time_slots = [];
for ($hour = 9; $hour <= 17; $hour++) {
    $time_slots[] = sprintf('%02d:00', $hour);
    $time_slots[] = sprintf('%02d:30', $hour);
}

// Service icons mapping
$service_icons = [
    'laptop' => 'fa-laptop-medical',
    'phone' => 'fa-mobile-alt',
    'repair' => 'fa-tools',
    'installation' => 'fa-plug',
    'network' => 'fa-network-wired',
    'wifi' => 'fa-wifi',
    'software' => 'fa-laptop-code',
    'virus' => 'fa-virus-slash',
    'consultation' => 'fa-comments',
    'diagnostics' => 'fa-stethoscope',
    'screen' => 'fa-mobile-screen',
    'battery' => 'fa-battery-full',
];

function get_service_icon($name) {
    global $service_icons;
    $name = strtolower($name);
    foreach ($service_icons as $key => $icon) {
        if (strpos($name, $key) !== false) {
            return $icon;
        }
    }
    return 'fa-tools'; // default
}
?>

<!-- Page Header -->
<section class="bg-light py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="mb-2">Our Services</h1>
                <p class="text-muted mb-0">
                    Professional tech services - Diagnostics, repairs, ISP setup & more in Mlolongo
                </p>
            </div>
            <div class="col-md-4 text-md-end">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb justify-content-md-end mb-0">
                        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>">Home</a></li>
                        <li class="breadcrumb-item active">Services</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</section>

<div class="container py-5">
    <!-- Why Choose Our Services -->
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="text-center p-4">
                <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px;">
                    <i class="fas fa-clock fa-2x text-primary"></i>
                </div>
                <h5>Quick Turnaround</h5>
                <p class="text-muted small mb-0">Most repairs completed within 24-48 hours</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="text-center p-4">
                <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px;">
                    <i class="fas fa-shield-alt fa-2x text-success"></i>
                </div>
                <h5>Warranty on Repairs</h5>
                <p class="text-muted small mb-0">30-day warranty on all repair services</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="text-center p-4">
                <div class="bg-info bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px;">
                    <i class="fas fa-user-check fa-2x text-info"></i>
                </div>
                <h5>Expert Technicians</h5>
                <p class="text-muted small mb-0">Certified professionals with years of experience</p>
            </div>
        </div>
    </div>

    <!-- Services Grid -->
    <h2 class="fw-bold mb-4 text-center">Available Services</h2>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <p class="mb-1"><?= htmlspecialchars($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <?php if (empty($services)): ?>
            <div class="col-12 text-center py-5 text-muted">
                <i class="fas fa-tools fa-4x mb-3"></i>
                <h4>No services available yet</h4>
                <p>Check back soon for our service offerings.</p>
            </div>
        <?php else: ?>
            <?php foreach ($services as $index => $s): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm service-card">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-start mb-3">
                                <div class="bg-primary bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 60px; height: 60px; min-width: 60px;">
                                    <i class="fas <?= get_service_icon($s['name']) ?> fa-xl text-primary"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="card-title fw-bold mb-1"><?= htmlspecialchars($s['name']) ?></h5>
                                    <?php if (!empty($s['duration_minutes'])): ?>
                                        <span class="badge bg-light text-dark">
                                            <i class="fas fa-clock me-1"></i> ~<?= $s['duration_minutes'] ?> min
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <p class="card-text text-muted mb-4">
                                <?= nl2br(htmlspecialchars($s['description'] ?: 'Quality service guaranteed by our expert technicians.')) ?>
                            </p>
                            
                            <div class="d-flex justify-content-between align-items-center mt-auto">
                                <div>
                                    <span class="fs-4 fw-bold text-primary">
                                        KSh <?= number_format($s['price']) ?>
                                    </span>
                                </div>
                                <button class="btn btn-primary rounded-pill px-4 book-service"
                                        data-bs-toggle="modal"
                                        data-bs-target="#bookingModal"
                                        data-id="<?= $s['id'] ?>"
                                        data-name="<?= htmlspecialchars($s['name']) ?>"
                                        data-price="<?= $s['price'] ?>"
                                        data-duration="<?= $s['duration_minutes'] ?? 60 ?>">
                                    <i class="fas fa-calendar-check me-2"></i> Book Now
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- CTA Section -->
    <div class="card bg-primary text-white border-0 mt-5">
        <div class="card-body text-center py-5">
            <h3 class="fw-bold mb-3">Need a Custom Service?</h3>
            <p class="mb-4" style="opacity: 0.9;">Contact us for specialized services or bulk bookings</p>
            <a href="tel:+254700000000" class="btn btn-light btn-lg me-2">
                <i class="fas fa-phone me-2"></i> Call Us
            </a>
            <a href="https://wa.me/254700000000" class="btn btn-outline-light btn-lg">
                <i class="fab fa-whatsapp me-2"></i> WhatsApp
            </a>
        </div>
    </div>
</div>

<!-- Booking Modal -->
<div class="modal fade" id="bookingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-calendar-check me-2 text-primary"></i>Book Service
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong id="modalServiceName"></strong>
                            </div>
                            <div class="text-primary fw-bold">
                                KSh <span id="modalServicePrice"></span>
                            </div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="service_id" id="serviceId" value="">
                    <input type="hidden" name="book_service" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Date</label>
                        <input type="date" class="form-control" name="booking_date" required min="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Time</label>
                        <select class="form-select" name="booking_time" required>
                            <option value="">Choose time...</option>
                            <?php foreach ($time_slots as $slot): ?>
                                <option value="<?= $slot ?>"><?= $slot ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Your Phone Number</label>
                        <input type="tel" class="form-control" name="phone" placeholder="07XX XXX XXX" required pattern="0[17]\d{8}">
                        <small class="text-muted">We'll call to confirm your booking</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Additional Notes (optional)</label>
                        <textarea class="form-control" name="notes" rows="2" placeholder="Describe the issue or any special requests..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check-circle me-2"></i> Confirm Booking
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.service-card {
    transition: transform 0.3s, box-shadow 0.3s;
}
.service-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1) !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const bookingModal = document.getElementById('bookingModal');
    
    // Check for book parameter to auto-open modal
    const urlParams = new URLSearchParams(window.location.search);
    const bookService = urlParams.get('book');
    if (bookService) {
        const button = document.querySelector('.book-service[data-id="' + bookService + '"]');
        if (button) {
            const modal = new bootstrap.Modal(bookingModal);
            bookingModal.addEventListener('shown.bs.modal', function() {
                document.getElementById('serviceId').value = bookService;
                const service = document.querySelector('.book-service[data-id="' + bookService + '"]');
                if (service) {
                    document.getElementById('modalServiceName').textContent = service.dataset.name;
                    document.getElementById('modalServicePrice').textContent = parseInt(service.dataset.price).toLocaleString();
                }
            });
            modal.show();
        } else {
            // If button not found and user not logged in, redirect to login
            <?php if (!isset($_SESSION['user_id'])): ?>
                window.location.href = 'login.php?redirect=services.php?book=' + bookService;
            <?php endif; ?>
        }
    }
    
    bookingModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        
        // Check if user is logged in
        <?php if (!isset($_SESSION['user_id'])): ?>
            const currentUrl = window.location.href;
            const redirectUrl = currentUrl.includes('book=') ? currentUrl : 'login.php?redirect=' + encodeURIComponent(window.location.href + (window.location.search ? '&' : '?') + 'book=' + (button ? button.dataset.id : ''));
            event.preventDefault();
            alert('Please login to book a service. Redirecting to login...');
            window.location.href = redirectUrl;
            return;
        <?php endif; ?>
        
        document.getElementById('serviceId').value = button.dataset.id;
        document.getElementById('modalServiceName').textContent = button.dataset.name;
        document.getElementById('modalServicePrice').textContent = parseInt(button.dataset.price).toLocaleString();
    });
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
