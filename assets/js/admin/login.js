function togglePw() {
  const i = document.getElementById('password'),
     e = document.getElementById('eye-icon'),
     s = i.type === 'password';
  i.type = s ? 'text' : 'password';
  e.className = s ? 'fas fa-eye-slash text-secondary small' : 'fas fa-eye text-secondary small';
}