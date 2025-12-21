</div> <!-- End of .p-4 -->
        
        <!-- Footer -->
        <footer class="mt-auto py-4 bg-white text-center border-top">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-6 text-md-start mb-2 mb-md-0">
                        <small class="text-muted fw-bold">&copy; <?php echo date('Y'); ?> ระบบบริหารจัดการศูนย์พักพิงชั่วคราว</small>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <small class="text-muted">Official Version 1.0 <i class="fas fa-check-circle text-success ms-1"></i></small>
                    </div>
                </div>
            </div>
        </footer>

    </div> <!-- End #content -->
</div> <!-- End .wrapper -->

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const content = document.getElementById('content');
        const btnToggle = document.getElementById('sidebarCollapse');
        const overlay = document.getElementById('mobileOverlay');
        
        // Function to check mobile view
        const isMobile = () => window.innerWidth < 992;

        btnToggle.addEventListener('click', function() {
            if (isMobile()) {
                // Mobile: Toggle class 'show-mobile' and Overlay
                sidebar.classList.toggle('show-mobile');
                overlay.classList.toggle('show');
            } else {
                // Desktop: Toggle class 'collapsed' and Content 'expanded'
                sidebar.classList.toggle('collapsed');
                content.classList.toggle('expanded');
            }
        });

        // Click Overlay to Close (Mobile Only)
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show-mobile');
            overlay.classList.remove('show');
        });

        // Auto-Expand Active Submenu
        const activeLink = document.querySelector('#sidebar ul.components li a.active');
        if (activeLink) {
            const parentCollapse = activeLink.closest('.collapse');
            if (parentCollapse) {
                // Open Submenu
                new bootstrap.Collapse(parentCollapse, { toggle: true });
                
                // Highlight Parent Menu
                const parentToggle = document.querySelector(`a[href="#${parentCollapse.id}"]`);
                if (parentToggle) {
                    parentToggle.classList.add('text-white');
                    parentToggle.querySelector('i').classList.replace('text-warning', 'text-white'); // Change icon color
                    parentToggle.parentElement.style.backgroundColor = 'rgba(255,255,255,0.05)';
                    parentToggle.setAttribute('aria-expanded', 'true');
                }
            }
        }
    });
</script>

</body>
</html>