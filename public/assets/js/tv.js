(function () {
  const config = window.TV_APP_CONFIG || {};
  const stage = document.getElementById('tv-stage');
  const emptyState = document.getElementById('tv-empty-state');
  const progress = document.getElementById('tv-progress');
  const counter = document.getElementById('tv-counter');
  const lastUpdate = document.getElementById('tv-last-update');
  const status = document.getElementById('tv-status');
  const fullscreenButton = document.getElementById('tv-fullscreen-button');

  const ROTATION_MS = 9000;
  const REFRESH_MS = 20000;

  let posts = [];
  let currentIndex = 0;
  let rotationTimer = null;
  let refreshTimer = null;

  function formatDate(value) {
    if (!value) return '';

    const parsed = new Date(value.replace(' ', 'T'));
    if (Number.isNaN(parsed.getTime())) {
      return value;
    }

    return new Intl.DateTimeFormat('pt-BR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }).format(parsed);
  }

  function absolutize(path) {
    if (!path) return '';
    if (/^https?:\/\//i.test(path)) return path;
    return `${String(config.appBase || '').replace(/\/$/, '')}/${String(path).replace(/^\//, '')}`;
  }

  function setStatus(text) {
    if (status) {
      status.textContent = text;
    }
  }

  function renderProgress() {
    if (!progress) return;

    progress.innerHTML = '';
    posts.forEach((_, index) => {
      const dot = document.createElement('span');
      dot.className = `tv-progress__dot${index === currentIndex ? ' is-active' : ''}`;
      progress.appendChild(dot);
    });
  }

  function renderCounter() {
    if (!counter) return;

    const total = posts.length;
    counter.textContent = total === 1 ? '1 mensagem' : `${total} mensagens`;
  }

  function renderLastUpdate() {
    if (!lastUpdate) return;
    lastUpdate.textContent = `Atualizado em ${new Intl.DateTimeFormat('pt-BR', {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
    }).format(new Date())}`;
  }

  function renderCurrentPost() {
    if (!stage) return;

    if (!posts.length) {
      stage.innerHTML = '';
      if (emptyState) {
        stage.appendChild(emptyState);
        emptyState.hidden = false;
      }
      renderProgress();
      renderCounter();
      setStatus('Aguardando mensagens');
      return;
    }

    if (emptyState) {
      emptyState.hidden = true;
    }

    const post = posts[currentIndex];
    const imageHtml = post.imagem
      ? `<div class="tv-slide__media"><img src="${absolutize(post.imagem)}" alt="Imagem da homenagem"></div>`
      : '';

    const authorPhoto = post.foto_autor
      ? `<img src="${absolutize(post.foto_autor)}" alt="${post.nome_autor || 'Autor'}">`
      : `<span>${String(post.nome_autor || 'M').trim().charAt(0).toUpperCase()}</span>`;

    stage.innerHTML = `
      <article class="tv-slide">
        <div class="tv-slide__header">
          <div class="tv-slide__author">
            <div class="tv-slide__avatar">${authorPhoto}</div>
            <div class="tv-slide__meta">
              <strong>${post.nome_autor || 'Mensagem anonima'}</strong>
              <span>${formatDate(post.criado_em)}</span>
            </div>
          </div>
        </div>
        <div class="tv-slide__body">
          ${post.texto ? `<div class="tv-slide__text">${post.texto}</div>` : ''}
          ${imageHtml}
        </div>
      </article>
    `;

    renderProgress();
    renderCounter();
    setStatus(`Exibindo ${currentIndex + 1} de ${posts.length}`);
  }

  function goToNext() {
    if (!posts.length) return;
    currentIndex = (currentIndex + 1) % posts.length;
    renderCurrentPost();
  }

  function startRotation() {
    clearInterval(rotationTimer);
    if (posts.length > 1) {
      rotationTimer = window.setInterval(goToNext, ROTATION_MS);
    }
  }

  async function loadFeed() {
    if (!config.memorialKey) {
      setStatus('Memorial nao informado');
      return;
    }

    try {
      const response = await fetch(`${config.apiBase}/feed.php?memorial_key=${encodeURIComponent(config.memorialKey)}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
      const payload = await response.json();

      if (!payload.success || !payload.data?.memorial_exists) {
        posts = [];
        currentIndex = 0;
        renderCurrentPost();
        setStatus('Memorial nao encontrado');
        return;
      }

      const incomingPosts = Array.isArray(payload.data.posts) ? payload.data.posts : [];
      const currentPostId = posts[currentIndex]?.id ?? null;
      posts = incomingPosts;

      if (!posts.length) {
        currentIndex = 0;
      } else {
        const preservedIndex = currentPostId ? posts.findIndex((post) => post.id === currentPostId) : -1;
        currentIndex = preservedIndex >= 0 ? preservedIndex : 0;
      }

      renderCurrentPost();
      renderLastUpdate();
      startRotation();
    } catch (error) {
      setStatus('Falha ao atualizar o mural');
    }
  }

  function toggleFullscreen() {
    if (!document.fullscreenElement) {
      document.documentElement.requestFullscreen?.();
      return;
    }

    document.exitFullscreen?.();
  }

  if (fullscreenButton) {
    fullscreenButton.addEventListener('click', toggleFullscreen);
  }

  loadFeed();
  refreshTimer = window.setInterval(loadFeed, REFRESH_MS);

  window.addEventListener('beforeunload', () => {
    clearInterval(rotationTimer);
    clearInterval(refreshTimer);
  });
})();
