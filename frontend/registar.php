<?php
session_start();

// Captura as mensagens de erro, se existirem
$nameError = isset($_SESSION['erro']) && $_SESSION['erro'] == 'Nome de utilizador já registado.' ? 'Nome de utilizador já registado.' : '';
$emailError = isset($_SESSION['erro']) && $_SESSION['erro'] == 'Email já registado.' ? 'Email já registado.' : '';

// Limpar as mensagens de erro após capturá-las
unset($_SESSION['erro']);
?>

<!DOCTYPE html>
<html lang="pt">

<head>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar</title>
    <link rel="stylesheet" href="css/style_registar.css">
    <link rel="icon" type="image/x-icon" href="images/favicon_orange.png">
</head>

<body>
    <header>
        <nav class="user"></nav>
    </header>

    <div class="signup-container">
        <img src="images/favicon/favicon_orange.png" alt="Orange Logo" class="logo">
        <h2>Criar Conta</h2>
        <form id="signupForm" action="../backend/registar.php" method="POST">

            <div class="form-row">
                <div class="form-group">
                    <input type="text" name="name" id="name" placeholder="Nome" maxlength="50" required>
                    <p class="error-text" id="nameError">Nome deve ter pelo menos 3 caracteres.</p>
                </div>

                <div class="form-group">
                    <input type="email" id="email" name="email" required placeholder="E-mail">
                    <p class="error-text" id="emailError">Email já está em uso.</p>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <input type="date" id="date" name="date" required min="1924-01-01" max="2011-12-31">
                    <p class="error-text" id="dateError"></p>
                </div>

                <div class="form-group">
                    <input type="text" name="nick" id="nick" placeholder="Nome de utilizador" maxlength="16" required>
                    <span class="info-icon"
                        data-tooltip="O seu nome de utilizador deve ter entre 3 e 16 caracteres, e pode conter letras, números, pontos e sublinhados. Não são permitidos espaços.">
                        <svg xmlns="http://www.w3.org/2000/svg" height="35" width="35" fill="#757575"
                            viewBox="0 0 16 16">
                            <path
                                d="M8 1.5A6.5 6.5 0 1 0 8 14.5A6.5 6.5 0 0 0 8 1.5zm0 12A5.5 5.5 0 1 1 8 2.5A5.5 5.5 0 0 1 8 13.5zM7.75 5.5h.5a.25.25 0 0 1 .25.25v.5a.25.25 0 0 1-.25.25h-.5A.25.25 0 0 1 7.5 6.25v-.5a.25.25 0 0 1 .25-.25zM7.5 7h1v5h-1z" />
                        </svg></span>
                    <p class="error-text" id="nickError">Nome de utilizador inválido.</p>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <input type="password" id="password" name="password" required placeholder="Palavra-passe" minlength="6">
                    <p class="error-text" id="passwordError">A palavra-passe deve ter pelo menos 6 caracteres.</p>
                </div>

                <div class="form-group">
                    <input type="password" id="confirm_password" name="confirm_password" required
                        placeholder="Confirmar palavra-passe">
                    <p class="error-text" id="confirmPasswordError">As palavras-passe não coincidem.</p>
                </div>
            </div>

            <div class="form-group">
                <label for="termos">
                    <input type="checkbox" id="termos" name="condicoes" required>
                    Li e aceito a <a href="privacidade.html" target="_blank">Política de Privacidade</a> e os <a
                        href="termos.html" target="_blank">Termos e Condições</a>
                </label>
                <button type="submit" class="btn" id="submitBtn" name="submitBtn" disabled>Criar Conta</button>
            </div>
        </form>
    </div>

    <footer>
        <p>&copy; 2024 ORANGE. Todos os direitos reservados.</p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('signupForm');
            const submitBtn = document.getElementById('submitBtn');
            
            // Inputs
            const nameInput = document.getElementById('name');
            const emailInput = document.getElementById('email');
            const dateInput = document.getElementById('date');
            const nickInput = document.getElementById('nick');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const termsCheckbox = document.getElementById('termos');
            
            // Error elements
            const nameError = document.getElementById('nameError');
            const emailError = document.getElementById('emailError');
            const dateError = document.getElementById('dateError');
            const nickError = document.getElementById('nickError');
            const passwordError = document.getElementById('passwordError');
            const confirmPasswordError = document.getElementById('confirmPasswordError');

            // Validation states
            const validationState = {
                name: false,
                email: false,
                date: false,
                nick: false,
                password: false,
                confirmPassword: false,
                terms: false
            };

            // Utility functions
            function showError(errorElement, message) {
                errorElement.textContent = message;
                errorElement.style.color = 'red';
            }

            function hideError(errorElement) {
                errorElement.style.color = 'transparent';
            }

            function updateSubmitButton() {
                const allValid = Object.values(validationState).every(state => state === true);
                submitBtn.disabled = !allValid;
            }

            function capitalizeName(name) {
                return name
                    .toLowerCase()
                    .replace(/\s+/g, ' ')
                    .trim()
                    .split(' ')
                    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                    .join(' ');
            }

            // Name validation
            function validateName() {
                const value = nameInput.value.trim();
                
                if (value.length === 0) {
                    hideError(nameError);
                    nameInput.classList.remove('incorreto');
                    validationState.name = false;
                } else if (value.length < 3) {
                    showError(nameError, 'Nome deve ter pelo menos 3 caracteres.');
                    nameInput.classList.add('incorreto');
                    validationState.name = false;
                } else if (/\d/.test(value)) {
                    showError(nameError, 'Nome não pode conter números.');
                    nameInput.classList.add('incorreto');
                    validationState.name = false;
                } else {
                    hideError(nameError);
                    nameInput.classList.remove('incorreto');
                    validationState.name = true;
                }
                
                updateSubmitButton();
            }

            // Nick validation
            function validateNick() {
                const value = nickInput.value.trim();
                
                if (value.length === 0) {
                    hideError(nickError);
                    nickInput.classList.remove('incorreto');
                    validationState.nick = false;
                } else if (value.length < 3) {
                    showError(nickError, 'Nome de utilizador deve ter pelo menos 3 caracteres.');
                    nickInput.classList.add('incorreto');
                    validationState.nick = false;
                } else if (value.length > 16) {
                    showError(nickError, 'Nome de utilizador deve ter no máximo 16 caracteres.');
                    nickInput.classList.add('incorreto');
                    validationState.nick = false;
                } else if (!/^[a-zA-Z0-9._]+$/.test(value)) {
                    showError(nickError, 'Nome de utilizador só pode conter letras, números, pontos e sublinhados.');
                    nickInput.classList.add('incorreto');
                    validationState.nick = false;
                } else if (/\s/.test(value)) {
                    showError(nickError, 'Nome de utilizador não pode conter espaços.');
                    nickInput.classList.add('incorreto');
                    validationState.nick = false;
                } else {
                    // Check if nick is available
                    checkNickAvailability(value);
                }
                
                updateSubmitButton();
            }

            // Email validation
            function validateEmail() {
                const value = emailInput.value.trim();
                
                if (value.length === 0) {
                    hideError(emailError);
                    emailInput.classList.remove('incorreto');
                    validationState.email = false;
                } else if (!value.includes('@') || !value.includes('.')) {
                    showError(emailError, 'Email inválido.');
                    emailInput.classList.add('incorreto');
                    validationState.email = false;
                } else {
                    // Check if email is available
                    checkEmailAvailability(value);
                }
                
                updateSubmitButton();
            }

            // Password validation
            function validatePassword() {
                const value = passwordInput.value;
                
                if (value.length === 0) {
                    hideError(passwordError);
                    passwordInput.classList.remove('incorreto');
                    validationState.password = false;
                } else if (value.length < 6) {
                    showError(passwordError, 'A palavra-passe deve ter pelo menos 6 caracteres.');
                    passwordInput.classList.add('incorreto');
                    validationState.password = false;
                } else {
                    hideError(passwordError);
                    passwordInput.classList.remove('incorreto');
                    validationState.password = true;
                }
                
                // Re-validate confirm password when password changes
                validateConfirmPassword();
                updateSubmitButton();
            }

            // Confirm password validation
            function validateConfirmPassword() {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                if (confirmPassword.length === 0) {
                    hideError(confirmPasswordError);
                    confirmPasswordInput.classList.remove('passred');
                    validationState.confirmPassword = false;
                } else if (password !== confirmPassword) {
                    showError(confirmPasswordError, 'As palavras-passe não coincidem.');
                    confirmPasswordInput.classList.add('passred');
                    validationState.confirmPassword = false;
                } else {
                    hideError(confirmPasswordError);
                    confirmPasswordInput.classList.remove('passred');
                    validationState.confirmPassword = true;
                }
                
                updateSubmitButton();
            }

            // Date validation
            function validateDate() {
                const value = dateInput.value;
                
                if (!value) {
                    hideError(dateError);
                    dateInput.classList.remove('incorreto');
                    validationState.date = false;
                    updateSubmitButton();
                    return;
                }

                const today = new Date();
                const selectedDate = new Date(value);
                let age = today.getFullYear() - selectedDate.getFullYear();
                const monthDiff = today.getMonth() - selectedDate.getMonth();
                const dayDiff = today.getDate() - selectedDate.getDate();

                if (monthDiff < 0 || (monthDiff === 0 && dayDiff < 0)) {
                    age--;
                }

                if (age < 13) {
                    showError(dateError, 'Tens de ser maior de 13 anos.');
                    dateInput.classList.add('incorreto');
                    validationState.date = false;
                } else if (age > 115) {
                    showError(dateError, 'Introduza uma data de nascimento real.');
                    dateInput.classList.add('incorreto');
                    validationState.date = false;
                } else {
                    hideError(dateError);
                    dateInput.classList.remove('incorreto');
                    validationState.date = true;
                }

                updateSubmitButton();
            }

            // Terms validation
            function validateTerms() {
                validationState.terms = termsCheckbox.checked;
                updateSubmitButton();
            }

            // API calls for availability checks
            async function checkNickAvailability(nick) {
                try {
                    const response = await fetch(`../backend/check_nick.php?nick=${encodeURIComponent(nick)}`);
                    const result = await response.text();
                    
                    if (result === 'exist') {
                        showError(nickError, 'Nome de utilizador já está em uso.');
                        nickInput.classList.add('incorreto');
                        validationState.nick = false;
                    } else {
                        hideError(nickError);
                        nickInput.classList.remove('incorreto');
                        validationState.nick = true;
                    }
                } catch (error) {
                    console.error('Erro ao verificar nick:', error);
                    showError(nickError, 'Erro ao verificar disponibilidade.');
                    validationState.nick = false;
                }
                
                updateSubmitButton();
            }

            async function checkEmailAvailability(email) {
                try {
                    const response = await fetch(`../backend/check_email.php?email=${encodeURIComponent(email)}`);
                    const result = await response.text();
                    
                    if (result === 'exist') {
                        showError(emailError, 'Email já está em uso.');
                        emailInput.classList.add('incorreto');
                        validationState.email = false;
                    } else {
                        hideError(emailError);
                        emailInput.classList.remove('incorreto');
                        validationState.email = true;
                    }
                } catch (error) {
                    console.error('Erro ao verificar email:', error);
                    showError(emailError, 'Erro ao verificar disponibilidade.');
                    validationState.email = false;
                }
                
                updateSubmitButton();
            }

            // Event listeners
            nameInput.addEventListener('input', function(e) {
                // Remove números enquanto digita
                this.value = this.value.replace(/[^a-zA-ZÀ-ÿ\s]/g, '');
                validateName();
            });

            nameInput.addEventListener('blur', function() {
                if (this.value.trim()) {
                    this.value = capitalizeName(this.value);
                    validateName();
                }
            });

            emailInput.addEventListener('input', validateEmail);
            emailInput.addEventListener('blur', validateEmail);

            dateInput.addEventListener('change', validateDate);
            dateInput.addEventListener('blur', validateDate);

            nickInput.addEventListener('input', function() {
                // Remove espaços enquanto digita
                this.value = this.value.replace(/\s/g, '');
                validateNick();
            });

            nickInput.addEventListener('blur', validateNick);

            passwordInput.addEventListener('input', validatePassword);
            confirmPasswordInput.addEventListener('input', validateConfirmPassword);
            termsCheckbox.addEventListener('change', validateTerms);

            // Form submission
            form.addEventListener('submit', function(e) {
                // Final validation before submit
                validateName();
                validateEmail();
                validateDate();
                validateNick();
                validatePassword();
                validateConfirmPassword();
                validateTerms();

                const allValid = Object.values(validationState).every(state => state === true);
                
                if (!allValid) {
                    e.preventDefault();
                    
                    // Show which fields are invalid
                    const invalidFields = [];
                    if (!validationState.name) invalidFields.push('Nome');
                    if (!validationState.email) invalidFields.push('Email');
                    if (!validationState.date) invalidFields.push('Data de nascimento');
                    if (!validationState.nick) invalidFields.push('Nome de utilizador');
                    if (!validationState.password) invalidFields.push('Palavra-passe');
                    if (!validationState.confirmPassword) invalidFields.push('Confirmação de palavra-passe');
                    if (!validationState.terms) invalidFields.push('Termos e condições');
                    
                    alert('Por favor, corrija os seguintes campos: ' + invalidFields.join(', '));
                    return false;
                }

                // Disable button to prevent double submission
                submitBtn.disabled = true;
                submitBtn.textContent = 'Criando conta...';
            });

            // Initialize validation state
            updateSubmitButton();
        });
    </script>
</body>

</html>