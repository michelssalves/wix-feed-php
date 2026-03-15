(function () {
  const config = window.TV_APP_CONFIG || {};
  const stage = document.getElementById('tv-stage');
  const emptyState = document.getElementById('tv-empty-state');
  const progress = document.getElementById('tv-progress');
  const counter = document.getElementById('tv-counter');
  const lastUpdate = document.getElementById('tv-last-update');
  const status = document.getElementById('tv-status');

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
    const hasImage = Boolean(post.imagem);
    const hasText = Boolean(post.texto);
    const imageHtml = post.imagem
      ? `<div class="tv-slide__media"><img src="${absolutize(post.imagem)}" alt="Imagem da homenagem"></div>`
      : '';

    const bylineHtml = `
      <div class="tv-slide__byline">
        <span class="tv-slide__byline-name">${post.nome_autor || 'Mensagem anonima'}</span>
        <span class="tv-slide__byline-separator">•</span>
        <span class="tv-slide__byline-date">${formatDate(post.criado_em)}</span>
      </div>
    `;

    stage.innerHTML = `
      <article class="tv-slide${hasImage ? ' tv-slide--with-media' : ' tv-slide--text-only'}">
        <div class="tv-slide__body">
          ${hasText ? `<div class="tv-slide__text-wrap"><div class="tv-slide__text">${post.texto}</div>${bylineHtml}</div>` : `<div class="tv-slide__text-wrap tv-slide__text-wrap--meta-only">${bylineHtml}</div>`}
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

  loadFeed();
  refreshTimer = window.setInterval(loadFeed, REFRESH_MS);

  window.addEventListener('beforeunload', () => {
    clearInterval(rotationTimer);
    clearInterval(refreshTimer);
  });
})();
