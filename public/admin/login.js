document.getElementById('login-form').addEventListener('submit', async (event) => {
  event.preventDefault();
  const username = document.getElementById('username').value;
  const password = document.getElementById('password').value;
  const error = document.getElementById('error');
  error.textContent = '';

  try {
    const response = await fetch('/auth.php/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username, password })
    });

    const data = await response.json();
    if (!response.ok) {
      error.textContent = data.error || 'No fue posible iniciar sesión';
      return;
    }

    localStorage.setItem('token', data.token);
    window.location.href = '/admin/agenda.html';
  } catch (err) {
    console.error(err);
    error.textContent = 'Error de conexión';
  }
});
