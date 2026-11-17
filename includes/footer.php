<?php
/**
 * includes/footer.php
 * Rodapé unificado com inclusão dos scripts do Bootstrap 5 via CDN.
 * Projeto: Copa Interclasse Handebol
 */
?>
    </main>

    <!-- Rodapé da Aplicação -->
    <footer class="bg-dark text-white text-center py-3 mt-auto border-top">
        <div class="container">
            <p class="mb-1 small text-white-50">
                &copy; <?= date('Y') ?> <strong>Copa Interclasse Handebol</strong> &bull; Sistema de Gestão Esportiva e Súmula Eletrônica
            </p>
            <p class="mb-0 text-secondary" style="font-size: 0.8rem;">
                Desenvolvido com PHP 8, POO, MySQL e Bootstrap 5
            </p>
        </div>
    </footer>

    <!-- Bootstrap 5 Bundle JS (com Popper) via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
