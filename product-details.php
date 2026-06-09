<?php
include 'includes/config.php';

// Get product ID from URL
if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$product_id = intval($_GET['id']);

// Get product details
$product_query = "SELECT p.*, u.name as farmer_name, u.farm_location FROM products p 
                  JOIN users u ON p.farmer_id = u.id 
                  WHERE p.id = ?";
$stmt = $conn->prepare($product_query);
$stmt->bind_param('i', $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: index.php');
    exit;
}

$product = $result->fetch_assoc();
$stmt->close();

// Handle review submission
$review_error = '';
$review_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_review'])) {
    if (!isLoggedIn() || getUserRole() !== ROLE_BUYER) {
        $review_error = 'You must be logged in as a buyer to leave a review';
    } else {
        $rating = intval($_POST['rating']);
        $review_text = sanitize($_POST['review_text']);
        $buyer_id = $_SESSION['user_id'];

        // Check if user has purchased this product
        $purchase_check = "SELECT oi.order_id FROM order_items oi 
                          JOIN orders o ON oi.order_id = o.id 
                          WHERE oi.product_id = ? AND o.user_id = ?";
        $check_stmt = $conn->prepare($purchase_check);
        $check_stmt->bind_param('ii', $product_id, $buyer_id);
        $check_stmt->execute();
        $purchase_result = $check_stmt->get_result();

        if ($purchase_result->num_rows === 0) {
            $review_error = 'You can only review products you have purchased';
        } elseif ($rating < 1 || $rating > 5) {
            $review_error = 'Rating must be between 1 and 5 stars';
        } else {
            $order_id = $purchase_result->fetch_assoc()['order_id'];
            
            // Insert or update review
            $review_insert = "INSERT INTO reviews (product_id, buyer_id, order_id, rating, review_text) 
                            VALUES (?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE rating = ?, review_text = ?";
            $review_stmt = $conn->prepare($review_insert);
            $review_stmt->bind_param('iiiiiss', $product_id, $buyer_id, $order_id, $rating, $review_text, $rating, $review_text);
            
            if ($review_stmt->execute()) {
                $review_success = 'Review submitted successfully!';
            } else {
                $review_error = 'Error submitting review';
            }
            $review_stmt->close();
        }
        $check_stmt->close();
    }
}

// Get reviews for this product
$reviews_query = "SELECT r.*, u.name as reviewer_name, u.email FROM reviews r 
                 JOIN users u ON r.buyer_id = u.id 
                 WHERE r.product_id = ? 
                 ORDER BY r.created_at DESC";
$reviews_stmt = $conn->prepare($reviews_query);
$reviews_stmt->bind_param('i', $product_id);
$reviews_stmt->execute();
$reviews_result = $reviews_stmt->get_result();
$reviews = [];
$total_rating = 0;
$review_count = $reviews_result->num_rows;

while ($row = $reviews_result->fetch_assoc()) {
    $reviews[] = $row;
    $total_rating += $row['rating'];
}
$reviews_stmt->close();

$average_rating = $review_count > 0 ? $total_rating / $review_count : 0;

// Get cart count
$cart_count = 0;
if (isLoggedIn()) {
    $cart_query = "SELECT SUM(quantity) as total FROM cart WHERE user_id = ?";
    $stmt = $conn->prepare($cart_query);
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $cart_result = $stmt->get_result();
    $cart_row = $cart_result->fetch_assoc();
    $cart_count = $cart_row['total'] ?? 0;
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - AGROBIASHARA</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header class="main-header">
        <div class="header-content">
            <a href="index.php" class="logo">
                <div class="logo-placeholder">
                    <i class="fas fa-leaf"></i>
                </div>
                <span>AGROBIASHARA</span>
            </a>

            <div style="flex: 1;"></div>

            <div class="header-nav">
                <?php if (isLoggedIn()): ?>
                    <span style="color: var(--text-dark); font-weight: 500;">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                    </span>
                    <a href="cart.php" style="position: relative;">
                        <i class="fas fa-shopping-cart"></i>
                        <?php if ($cart_count > 0): ?>
                            <span style="position: absolute; top: -8px; right: -8px; background: var(--accent-orange); color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700;">
                                <?php echo $cart_count; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <form method="POST" action="logout.php" style="display: inline;">
                        <button type="submit" class="logout-btn">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </button>
                    </form>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary" style="margin: 0;">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Breadcrumb -->
    <div style="padding: 1rem 2rem; background: #f9f9f9; border-bottom: 1px solid var(--border-color);">
        <a href="index.php" style="color: var(--primary-green); text-decoration: none;">
            <i class="fas fa-home"></i> Home
        </a>
        <span style="margin: 0 0.5rem; color: #999;">/</span>
        <span><?php echo htmlspecialchars($product['name']); ?></span>
    </div>

    <!-- Product Details -->
    <div class="dashboard-container" style="max-width: 1000px; margin-top: 2rem;">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; background: white; padding: 2rem; border-radius: 8px; box-shadow: var(--shadow); margin-bottom: 2rem;">
            <!-- Product Image -->
            <div>
                <div style="width: 100%; aspect-ratio: 1; border-radius: 8px; overflow: hidden; background: #f0f0f0; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                    <?php if ($product['image_url'] && file_exists($product['image_url'])): ?>
                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <i class="fas fa-image" style="font-size: 4rem; color: #ccc;"></i>
                    <?php endif; ?>
                </div>
                <p style="text-align: center; color: var(--text-light); font-size: 0.9rem;">
                    <i class="fas fa-check-circle" style="color: var(--primary-green);"></i> Product Image
                </p>
            </div>

            <!-- Product Info -->
            <div>
                <h1 style="color: var(--primary-green); margin-bottom: 1rem;"><?php echo htmlspecialchars($product['name']); ?></h1>
                
                <!-- Rating -->
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                    <div style="display: flex; gap: 0.2rem;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star" style="color: <?php echo $i <= round($average_rating) ? 'var(--accent-orange)' : '#ddd'; ?>; font-size: 1.2rem;"></i>
                        <?php endfor; ?>
                    </div>
                    <span style="color: var(--text-light);"><?php echo round($average_rating, 1); ?>/5.0</span>
                    <span style="color: #999; font-size: 0.9rem;"><?php echo $review_count; ?> review<?php echo $review_count !== 1 ? 's' : ''; ?></span>
                </div>

                <!-- Category -->
                <div style="background: #f0f0f0; padding: 0.5rem 1rem; border-radius: 4px; display: inline-block; margin-bottom: 1rem;">
                    <span style="font-size: 0.9rem; color: #666;">
                        <i class="fas fa-tag"></i> <?php echo ucfirst($product['category']); ?>
                    </span>
                </div>

                <!-- Description -->
                <h3 style="margin-top: 1.5rem; color: #333;">Description</h3>
                <p style="color: var(--text-dark); line-height: 1.6; margin-bottom: 1.5rem;">
                    <?php echo htmlspecialchars($product['description']) ?: 'No description provided'; ?>
                </p>

                <!-- Farmer Info -->
                <h3 style="color: #333;">Farmer</h3>
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                    <div style="width: 50px; height: 50px; background: var(--primary-green); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                        <i class="fas fa-user-tie" style="font-size: 1.5rem;"></i>
                    </div>
                    <div>
                        <p style="font-weight: 600; margin: 0;"><?php echo htmlspecialchars($product['farmer_name']); ?></p>
                        <p style="font-size: 0.9rem; color: var(--text-light); margin: 0;">
                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($product['farm_location'] ?? 'Location not specified'); ?>
                        </p>
                    </div>
                </div>

                <!-- Price and Stock -->
                <div style="background: #f9f9f9; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <div>
                            <span style="font-size: 0.9rem; color: var(--text-light);">Price</span>
                            <h2 style="color: var(--primary-green); margin: 0.5rem 0 0 0;">KES <?php echo number_format($product['price'], 2); ?></h2>
                        </div>
                        <div style="text-align: right;">
                            <span style="font-size: 0.9rem; color: var(--text-light);">Available Stock</span>
                            <p style="font-size: 1.5rem; color: #333; margin: 0.5rem 0 0 0; font-weight: 600;">
                                <?php echo $product['quantity']; ?> units
                            </p>
                        </div>
                    </div>

                    <!-- Add to Cart Button -->
                    <?php if ($product['quantity'] <= 0): ?>
                        <button class="btn btn-primary" style="width: 100%; padding: 1rem; opacity: 0.5; cursor: not-allowed;" disabled>
                            <i class="fas fa-times-circle"></i> Out of Stock
                        </button>
                    <?php elseif (isLoggedIn() && getUserRole() === ROLE_BUYER): ?>
                        <form method="POST" action="add-to-cart.php">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem;">
                                <i class="fas fa-shopping-cart"></i> Add to Cart
                            </button>
                        </form>
                    <?php elseif (!isLoggedIn()): ?>
                        <a href="login.php" class="btn btn-primary" style="width: 100%; padding: 1rem; display: block; text-align: center; text-decoration: none;">
                            <i class="fas fa-shopping-cart"></i> Login to Buy
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Reviews Section -->
        <div style="background: white; padding: 2rem; border-radius: 8px; box-shadow: var(--shadow);">
            <h2 style="color: var(--primary-green); margin-bottom: 2rem;">
                <i class="fas fa-comments"></i> Customer Reviews & Ratings
            </h2>

            <!-- Add Review Form -->
            <?php if (isLoggedIn() && getUserRole() === ROLE_BUYER): ?>
                <div style="background: #f9f9f9; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
                    <h3 style="margin-top: 0;">Write a Review</h3>

                    <?php if ($review_error): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $review_error; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($review_success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo $review_success; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" style="display: flex; flex-direction: column; gap: 1rem;">
                        <input type="hidden" name="add_review" value="1">

                        <div class="form-group">
                            <label for="rating">Rating *</label>
                            <div style="display: flex; gap: 0.5rem; font-size: 2rem; cursor: pointer;" id="rating-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star" data-rating="<?php echo $i; ?>" style="color: #ddd; cursor: pointer; transition: color 0.2s;" onclick="selectRating(<?php echo $i; ?>)"></i>
                                <?php endfor; ?>
                            </div>
                            <input type="hidden" id="rating" name="rating" value="0" required>
                        </div>

                        <div class="form-group">
                            <label for="review_text">Your Review</label>
                            <textarea id="review_text" name="review_text" placeholder="Share your experience with this product..." style="min-height: 100px;"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Submit Review
                        </button>
                    </form>
                </div>
            <?php elseif (!isLoggedIn()): ?>
                <div style="background: #e8f5e9; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; text-align: center;">
                    <p style="margin: 0; color: var(--primary-green);">
                        <i class="fas fa-info-circle"></i> 
                        <a href="login.php" style="color: var(--primary-green); font-weight: 600;">Login</a> to write a review
                    </p>
                </div>
            <?php endif; ?>

            <!-- Reviews List -->
            <div>
                <h3 style="margin-bottom: 1.5rem; border-bottom: 2px solid var(--border-color); padding-bottom: 1rem;">
                    All Reviews (<?php echo $review_count; ?>)
                </h3>

                <?php if (empty($reviews)): ?>
                    <div style="text-align: center; padding: 2rem; color: var(--text-light);">
                        <i class="fas fa-comment-slash" style="font-size: 2rem; display: block; margin-bottom: 1rem;"></i>
                        <p>No reviews yet. Be the first to review this product!</p>
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                        <?php foreach ($reviews as $review): ?>
                            <div style="padding: 1rem; border-left: 3px solid var(--primary-green); background: #fafafa; border-radius: 4px;">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                                    <div>
                                        <p style="font-weight: 600; margin: 0;"><?php echo htmlspecialchars($review['reviewer_name']); ?></p>
                                        <p style="font-size: 0.8rem; color: var(--text-light); margin: 0.3rem 0 0 0;">
                                            <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                                        </p>
                                    </div>
                                    <div style="display: flex; gap: 0.2rem;">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star" style="color: <?php echo $i <= $review['rating'] ? 'var(--accent-orange)' : '#ddd'; ?>; font-size: 0.9rem;"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <p style="margin: 0.5rem 0 0 0; color: var(--text-dark); line-height: 1.5;">
                                    <?php echo htmlspecialchars($review['review_text']) ?: 'No written review'; ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; 2024 AGROBIASHARA. Fresh Farm Produce Marketplace.</p>
        <p>Email: Agrobiashara@gmail.com | Phone: +254 722 736 023</p>
    </footer>

    <script>
        function selectRating(rating) {
            document.getElementById('rating').value = rating;
            const stars = document.querySelectorAll('#rating-stars i');
            stars.forEach((star, index) => {
                if (index < rating) {
                    star.style.color = 'var(--accent-orange)';
                } else {
                    star.style.color = '#ddd';
                }
            });
        }

        // Hover effect for stars
        const stars = document.querySelectorAll('#rating-stars i');
        stars.forEach((star) => {
            star.addEventListener('mouseenter', function() {
                const hoverRating = this.dataset.rating;
                stars.forEach((s, index) => {
                    if (index < hoverRating) {
                        s.style.color = 'var(--accent-orange)';
                    } else {
                        s.style.color = '#ddd';
                    }
                });
            });
        });

        document.getElementById('rating-stars').addEventListener('mouseleave', function() {
            const currentRating = document.getElementById('rating').value;
            stars.forEach((star, index) => {
                if (index < currentRating) {
                    star.style.color = 'var(--accent-orange)';
                } else {
                    star.style.color = '#ddd';
                }
            });
        });
    </script>
</body>
</html>
