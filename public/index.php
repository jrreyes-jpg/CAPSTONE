<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Edge Automation Technology Services, Co. - Professional engineering automation and technology solutions">
    <title>Edge Automation Technology Services, Co.</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/codesamplecaps/public/assets/css/landing.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <img src="assets/images/edge.jpg" alt="Edge Automation logo" class="logo-img">
                <span class="logo-text">EDGE AUTOMATION</span>
            </div>
            <ul class="nav-menu">
                <li><a href="#home" class="nav-link">Home</a></li>
                <li><a href="#about" class="nav-link">About</a></li>
                <li><a href="#services" class="nav-link">Services</a></li>
                <li><a href="#projects" class="nav-link">Projects</a></li>
                <li><a href="#contact" class="nav-link">Contact</a></li>
                <?php if (empty($_SESSION['user_id'])): ?>
                    <li><a href="/codesamplecaps/views/auth/register.php" class="nav-link btn btn-secondary">Register</a></li>
                    <li class="mobile-only"><a href="login.php" class="nav-link btn btn-primary">Login</a></li>
                <?php endif; ?>
            </ul>

            <?php if (empty($_SESSION['user_id'])): ?>
                <div class="nav-actions">
                    <a href="login.php" class="btn btn-primary">Login</a>
                </div>
            <?php else: ?>
                <div class="nav-actions">
                    <span class="welcome-text">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="logout.php" class="btn btn-secondary">Logout</a>
                </div>
            <?php endif; ?>

            <div class="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="hero-content">
            <h1 class="hero-title">Engineering Innovation at Scale</h1>
            <p class="hero-subtitle">Specialists in automation, electrical systems, and industrial solutions</p>
            <div class="cta-buttons">
                <a href="#contact" class="btn btn-primary">Request Consultation</a>
                <a href="#services" class="btn btn-secondary">View Services</a>
            </div>
        </div>
        <div class="hero-overlay"></div>
    </section>

    <!-- About Section -->
    <section id="about" class="about">
        <div class="container">
            <h2 class="section-title">About Us</h2>
            <div class="about-content">
                <p class="about-text">
                    Edge Automation Technology Services, Co. is a leading engineering firm specializing in mechanical engineering, electrical systems, and advanced automation solutions. With years of industry expertise, we deliver turnkey solutions for industrial clients seeking reliable, scalable, and innovative technology deployments.
                </p>
                <p class="about-text">
                    We combine deep technical knowledge with practical implementation experience to transform business challenges into competitive advantages through smart automation and systems integration.
                </p>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="services">
        <div class="container">
            <h2 class="section-title">Our Services</h2>
            <div class="services-grid">
                <!-- Mechanical Engineering -->
                <div class="service-card">
                    <div class="service-icon">⚙️</div>
                    <h3>Mechanical Engineering</h3>
                    <ul class="service-list">
                        <li>Utility Systems (Air, Water, Steam)</li>
                        <li>Fire Protection Systems</li>
                        <li>Diesel Generator Systems</li>
                        <li>Cleanroom Systems</li>
                        <li>HVAC Solutions</li>
                        <li>Preventive Maintenance</li>
                    </ul>
                </div>

                <!-- Electrical Engineering -->
                <div class="service-card">
                    <div class="service-icon">⚡</div>
                    <h3>Electrical Engineering</h3>
                    <ul class="service-list">
                        <li>Electrical Power System Analysis</li>
                        <li>Voltage Drop Calculations</li>
                        <li>Load Distribution Design</li>
                        <li>Arc Flash Studies</li>
                        <li>Transformer Installation</li>
                        <li>Capacitor Banks & Panels</li>
                    </ul>
                </div>

                <!-- Electronics & Automation -->
                <div class="service-card">
                    <div class="service-icon">🤖</div>
                    <h3>Electronics & Automation</h3>
                    <ul class="service-list">
                        <li>Factory Automation Systems</li>
                        <li>Building Management Systems</li>
                        <li>CCTV & Security Solutions</li>
                        <li>Structured Cabling</li>
                        <li>Fire Detection & Alarms</li>
                        <li>Production Machine Integration</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Specialized Projects Section -->
    <section id="projects" class="projects">
        <div class="container">
            <h2 class="section-title">Specialized Solutions</h2>
            <div class="projects-grid">
                <div class="project-item">
                    <span class="project-number">01</span>
                    <h4>Machine Improvement</h4>
                    <p>Enhance existing machinery performance and reliability</p>
                </div>
                <div class="project-item">
                    <span class="project-number">02</span>
                    <h4>Process Optimization</h4>
                    <p>Streamline operations and increase efficiency</p>
                </div>
                <div class="project-item">
                    <span class="project-number">03</span>
                    <h4>Solar Installation</h4>
                    <p>Renewable energy solutions for sustainable operations</p>
                </div>
                <div class="project-item">
                    <span class="project-number">04</span>
                    <h4>Solar Maintenance</h4>
                    <p>Ongoing support and optimization of solar systems</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Choose Us -->
    <section class="why-choose">
        <div class="container">
            <h2 class="section-title">Why Choose Us</h2>
            <div class="features-grid">
                <div class="feature">
                    <div class="feature-icon">🏆</div>
                    <h4>Industry Expertise</h4>
                    <p>Years of proven experience in industrial automation</p>
                </div>
                <div class="feature">
                    <div class="feature-icon">✅</div>
                    <h4>Reliable Systems</h4>
                    <p>Engineered for stability and long-term performance</p>
                </div>
                <div class="feature">
                    <div class="feature-icon">📈</div>
                    <h4>Scalable Solutions</h4>
                    <p>Grow your operations with our flexible systems</p>
                </div>
                <div class="feature">
                    <div class="feature-icon">🔧</div>
                    <h4>End-to-End Support</h4>
                    <p>From planning through implementation and beyond</p>
                </div>
                <div class="feature">
                    <div class="feature-icon">🛡️</div>
                    <h4>Preventive Care</h4>
                    <p>Minimize downtime with proactive maintenance</p>
                </div>
                <div class="feature">
                    <div class="feature-icon">💡</div>
                    <h4>Innovation</h4>
                    <p>Cutting-edge technology and best practices</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section (Login-gated) -->
    <section id="contact" class="contact">
        <div class="container">
            <h2 class="section-title">Get In Touch</h2>
            <div class="contact-content">
                <?php if (empty($_SESSION['user_id'])): ?>
                    <div class="login-card">
                        <h3>Login Required</h3>
                        <p>You must be logged in to send us a message.</p>
                        <div class="login-card-actions">
                            <a href="login.php" class="btn btn-primary">Login</a>
                            <a href="/codesamplecaps/views/auth/register.php" class="btn btn-secondary">Create Account</a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); } ?>
                    <form class="contact-form" id="contactForm" method="post" action="contact_submit.php">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="form-group">
                            <label for="name">Name</label>
                            <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($_SESSION['user_name']); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" required placeholder="your@email.com" value="<?php echo isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="message">Message</label>
                            <textarea id="message" name="message" required placeholder="Tell us about your project..." rows="6"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Send Message</button>
                    </form>
                <?php endif; ?>

                <div class="contact-info">
                    <h3>Contact Information</h3>
                    <p><strong>Company:</strong> Edge Automation Technology Services, Co.</p>
                    <p><strong>Specialization:</strong> Industrial Automation & Engineering</p>
                    <div class="social-links">
                        <a href="https://www.facebook.com/edgeautomationtsc" target="_blank" rel="noopener noreferrer" class="social-icon" aria-label="Facebook">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true">
                                <path fill="currentColor" d="M22.675 0h-21.35C.597 0 0 .597 0 1.326v21.348C0 23.403.597 24 1.326 24H12.82v-9.294H9.692V11.01h3.128V8.412c0-3.1 1.894-4.788 4.659-4.788 1.325 0 2.463.099 2.795.143v3.24l-1.918.001c-1.504 0-1.796.716-1.796 1.763v2.312h3.587l-.467 3.696h-3.12V24h6.116c.73 0 1.326-.597 1.326-1.326V1.326C24 .597 23.403 0 22.675 0z"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2026 Edge Automation Technology Services, Co. All rights reserved.</p>
        </div>
    </footer>

    <script src="/codesamplecaps/public/assets/js/landing.js"></script>
</body>
</html>
