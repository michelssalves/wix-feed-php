const state = {
  currentUser: window.APP_CONFIG.currentUser || null,
  posts: [],
  isEmbedded: window.self !== window.top,
  memorialKey: window.APP_CONFIG.memorialKey || '',
  memorialExists: true,
};

const elements = {
  flash: document.getElementById('flash-message'),
  postForm: document.getElementById('post-form'),
  postIdentityRow: document.getElementById('post-identity-row'),
  postAuthorName: document.getElementById('post-author-name'),
  postEditor: document.getElementById('post-editor'),
  postText: document.getElementById('post-text'),
  postImage: document.getElementById('post-image'),
  postImageTrigger: document.getElementById('post-image-trigger'),
  postSubmitButton: document.getElementById('post-submit-button'),
  selectedFileName: document.getElementById('selected-file-name'),
  attachmentStatusText: document.getElementById('attachment-status-text'),
  feedList: document.getElementById('feed-list'),
  emptyFeed: document.getElementById('empty-feed'),
  imageLightbox: document.getElementById('image-lightbox'),
  lightboxImage: document.getElementById('lightbox-image'),
  lightboxClose: document.getElementById('lightbox-close'),
  userSession: document.getElementById('user-session'),
  sessionAvatar: document.getElementById('session-avatar'),
  sessionName: document.getElementById('session-name'),
  logoutButton: document.getElementById('logout-button'),
};

function showMessage(message, type = 'success') {
  elements.flash.textContent = message;
  elements.flash.className = `flash-message is-${type}`;
  elements.flash.hidden = false;

  clearTimeout(showMessage.timeoutId);
  showMessage.timeoutId = setTimeout(() => {
    elements.flash.hidden = true;
  }, 5000);
}

function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function initials(name) {
  const clean = String(name || '').trim();
  if (!clean) return '?';
  return clean.split(/\s+/).slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join('');
}

function formatDate(value) {
  return new Intl.DateTimeFormat('pt-BR', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(String(value).replace(' ', 'T')));
}

function avatarMarkup(name, photoUrl, large = false) {
  const sizeClass = large ? ' avatar-lg' : '';
  const fallback = `<span class="avatar-fallback">${escapeHtml(initials(name))}</span>`;
  if (photoUrl) {
    return `<div class="avatar${sizeClass}">${fallback}<img src="${escapeHtml(photoUrl)}" alt="" referrerpolicy="no-referrer" onerror="this.remove();this.parentElement.classList.add('avatar--fallback');"></div>`;
  }
  return `<div class="avatar avatar--fallback${sizeClass}">${fallback}</div>`;
}

function escapeAttribute(value) {
  return escapeHtml(value).replaceAll('`', '&#096;');
}

function plainTextFromHtml(html) {
  const temp = document.createElement('div');
  temp.innerHTML = html;
  return (temp.textContent || temp.innerText || '').trim();
}

async function request(url, options = {}) {
  const response = await fetch(url, {
    credentials: 'include',
    ...options,
  });

  const data = await response.json().catch(() => ({
    success: false,
    message: 'Resposta invalida do servidor.',
  }));

  if (!response.ok || !data.success) {
    throw new Error(data.message || 'Falha na requisicao.');
  }

  return data;
}

function syncSessionUI() {
  const user = state.currentUser;

  if (!user) {
    elements.userSession.classList.add('is-hidden');
    elements.postIdentityRow.classList.remove('is-hidden');
    elements.postAuthorName.value = '';
    elements.postAuthorName.readOnly = false;
    return;
  }

  elements.userSession.classList.remove('is-hidden');
  elements.postIdentityRow.classList.add('is-hidden');
  elements.sessionName.textContent = user.name || '';
  elements.sessionAvatar.innerHTML = avatarMarkup(user.name, user.photo_url, true);
  elements.postAuthorName.value = user.name || '';
  elements.postAuthorName.readOnly = true;
}

function syncMemorialState() {
  const disabled = !state.memorialKey || !state.memorialExists;
  elements.postEditor.setAttribute('contenteditable', disabled ? 'false' : 'true');
  elements.postEditor.classList.toggle('is-disabled', disabled);
  elements.postImageTrigger.disabled = disabled;
  elements.postSubmitButton.disabled = disabled;
}

function commentFormMarkup(postId) {
  const identityClass = state.currentUser ? ' is-hidden' : '';
  return `
    <form class="comment-form is-hidden" data-comment-form="${postId}">
      <div class="comment-field-row${identityClass}">
        <label class="field">
          <span>Nome</span>
          <input type="text" name="author_name" maxlength="120" placeholder="Digite seu nome" ${state.currentUser ? 'readonly' : ''} value="${escapeHtml(state.currentUser?.name || '')}">
        </label>
        <div class="field">
          <span>Google</span>
          <div class="google-login-slot"><div class="google-button js-google-btn"></div></div>
        </div>
      </div>
      <label class="field">
        <textarea name="text" rows="3" placeholder="Escreva uma resposta"></textarea>
      </label>
      <div class="comment-form-actions">
        <button class="secondary-button" type="button" data-cancel-reply="${postId}">Cancelar</button>
        <button class="primary-button" type="submit">Enviar</button>
      </div>
    </form>
  `;
}

function canManageComment(comment) {
  return !!(state.currentUser && comment && Number(comment.user_id) > 0 && Number(comment.user_id) === Number(state.currentUser.id));
}

function canManagePost(post) {
  return !!(state.currentUser && post && Number(post.user_id) > 0 && Number(post.user_id) === Number(state.currentUser.id));
}

function renderFeed() {
  elements.feedList.innerHTML = '';
  elements.emptyFeed.hidden = state.posts.length > 0;

  state.posts.forEach((post) => {
    const article = document.createElement('article');
    article.className = 'post-card';
    article.innerHTML = `
      <div class="post-header">
        ${avatarMarkup(post.nome_autor, post.foto_autor)}
        <div class="post-header-copy">
          <div class="author-meta">
            <strong>${escapeHtml(post.nome_autor)}</strong>
          </div>
          <span class="post-date">${escapeHtml(formatDate(post.criado_em))}</span>
        </div>
        ${canManagePost(post) ? `
          <div class="comment-actions-menu">
            <button class="comment-action-button" type="button" data-post-edit-toggle="${post.id}">Editar</button>
            <button class="comment-action-button is-danger" type="button" data-post-delete="${post.id}">Excluir</button>
          </div>
        ` : ''}
      </div>
      <div class="post-body">
        <div class="post-rich-text">${post.texto}</div>
        ${post.imagem ? `<button class="post-image-button" type="button" data-image-view="${escapeAttribute(post.imagem)}" aria-label="Abrir imagem em tela cheia"><img class="post-image" src="${escapeHtml(post.imagem)}" alt="Imagem da postagem"></button>` : ''}
      </div>
      ${canManagePost(post) ? `
        <form class="post-edit-form is-hidden" data-post-edit-form="${post.id}">
          <label class="field">
            <textarea name="text" rows="4" placeholder="Edite sua postagem">${plainTextFromHtml(post.texto)}</textarea>
          </label>
          <div class="comment-form-actions">
            <button class="secondary-button" type="button" data-post-edit-cancel="${post.id}">Cancelar</button>
            <button class="primary-button" type="submit">Salvar</button>
          </div>
        </form>
      ` : ''}
      <div class="post-actions">
        <button class="reply-toggle" type="button" data-reply-toggle="${post.id}">Responder</button>
      </div>
      <div class="comments-list">
        ${(post.comments || []).map((comment) => `
          <div class="comment-card">
            <div class="comment-header">
              ${avatarMarkup(comment.nome_autor, comment.foto_autor)}
              <div class="post-header-copy">
                <div class="author-meta">
                  <strong>${escapeHtml(comment.nome_autor)}</strong>
                </div>
                <span class="comment-date">${escapeHtml(formatDate(comment.criado_em))}</span>
              </div>
              ${canManageComment(comment) ? `
                <div class="comment-actions-menu">
                  <button class="comment-action-button" type="button" data-comment-edit-toggle="${comment.id}">Editar</button>
                  <button class="comment-action-button is-danger" type="button" data-comment-delete="${comment.id}">Excluir</button>
                </div>
              ` : ''}
            </div>
            <div class="comment-body">
              <p>${escapeHtml(comment.texto)}</p>
            </div>
            ${canManageComment(comment) ? `
              <form class="comment-edit-form is-hidden" data-comment-edit-form="${comment.id}">
                <label class="field">
                  <textarea name="text" rows="3" placeholder="Edite seu comentario">${escapeHtml(comment.texto)}</textarea>
                </label>
                <div class="comment-form-actions">
                  <button class="secondary-button" type="button" data-comment-edit-cancel="${comment.id}">Cancelar</button>
                  <button class="primary-button" type="submit">Salvar</button>
                </div>
              </form>
            ` : ''}
          </div>
        `).join('')}
      </div>
      ${commentFormMarkup(post.id)}
    `;
    elements.feedList.appendChild(article);
  });

  bindCommentActions();
  bindImageActions();
  renderGoogleButtons();
}

function openLightbox(src) {
  elements.lightboxImage.src = src;
  elements.imageLightbox.classList.remove('is-hidden');
  elements.imageLightbox.setAttribute('aria-hidden', 'false');
  document.body.classList.add('is-lightbox-open');
}

function closeLightbox() {
  elements.imageLightbox.classList.add('is-hidden');
  elements.imageLightbox.setAttribute('aria-hidden', 'true');
  elements.lightboxImage.src = '';
  document.body.classList.remove('is-lightbox-open');
}

function bindImageActions() {
  document.querySelectorAll('[data-image-view]').forEach((button) => {
    button.onclick = () => {
      const src = button.getAttribute('data-image-view');
      if (src) {
        openLightbox(src);
      }
    };
  });
}

function bindCommentActions() {
  document.querySelectorAll('[data-reply-toggle]').forEach((button) => {
    button.onclick = () => {
      const postId = button.getAttribute('data-reply-toggle');
      const form = document.querySelector(`[data-comment-form="${postId}"]`);
      if (form) form.classList.toggle('is-hidden');
    };
  });

  document.querySelectorAll('[data-cancel-reply]').forEach((button) => {
    button.onclick = () => {
      const postId = button.getAttribute('data-cancel-reply');
      const form = document.querySelector(`[data-comment-form="${postId}"]`);
      if (form) form.classList.add('is-hidden');
    };
  });

  document.querySelectorAll('[data-comment-form]').forEach((form) => {
    form.onsubmit = async (event) => {
      event.preventDefault();
      const postId = Number(form.getAttribute('data-comment-form'));
      const formData = new FormData(form);

      try {
        await request(`${window.APP_CONFIG.apiBase}/create-comment.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            post_id: postId,
            memorial_key: state.memorialKey,
            author_name: formData.get('author_name'),
            text: formData.get('text'),
          }),
        });
        showMessage('Comentario enviado com sucesso.');
        form.reset();
        const authorInput = form.querySelector('[name="author_name"]');
        if (state.currentUser && authorInput) {
          authorInput.value = state.currentUser.name || '';
        }
        form.classList.add('is-hidden');
        await loadFeed();
      } catch (error) {
        showMessage(error.message, 'error');
      }
    };
  });

  document.querySelectorAll('[data-post-edit-toggle]').forEach((button) => {
    button.onclick = () => {
      const postId = button.getAttribute('data-post-edit-toggle');
      const form = document.querySelector(`[data-post-edit-form="${postId}"]`);
      if (form) {
        form.classList.toggle('is-hidden');
      }
    };
  });

  document.querySelectorAll('[data-post-edit-cancel]').forEach((button) => {
    button.onclick = () => {
      const postId = button.getAttribute('data-post-edit-cancel');
      const form = document.querySelector(`[data-post-edit-form="${postId}"]`);
      if (form) {
        form.classList.add('is-hidden');
      }
    };
  });

  document.querySelectorAll('[data-post-edit-form]').forEach((form) => {
    form.onsubmit = async (event) => {
      event.preventDefault();
      const postId = Number(form.getAttribute('data-post-edit-form'));
      const textarea = form.querySelector('[name="text"]');
      const text = textarea?.value?.trim() || '';

      try {
        await request(`${window.APP_CONFIG.apiBase}/update-post.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            post_id: postId,
            memorial_key: state.memorialKey,
            text,
          }),
        });
        showMessage('Postagem atualizada com sucesso.');
        await loadFeed();
      } catch (error) {
        showMessage(error.message, 'error');
      }
    };
  });

  document.querySelectorAll('[data-post-delete]').forEach((button) => {
    button.onclick = async () => {
      const postId = Number(button.getAttribute('data-post-delete'));
      if (!window.confirm('Deseja excluir esta postagem?')) {
        return;
      }

      try {
        await request(`${window.APP_CONFIG.apiBase}/delete-post.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            post_id: postId,
          }),
        });
        showMessage('Postagem excluida com sucesso.');
        await loadFeed();
      } catch (error) {
        showMessage(error.message, 'error');
      }
    };
  });

  document.querySelectorAll('[data-comment-edit-toggle]').forEach((button) => {
    button.onclick = () => {
      const commentId = button.getAttribute('data-comment-edit-toggle');
      const form = document.querySelector(`[data-comment-edit-form="${commentId}"]`);
      if (form) {
        form.classList.toggle('is-hidden');
      }
    };
  });

  document.querySelectorAll('[data-comment-edit-cancel]').forEach((button) => {
    button.onclick = () => {
      const commentId = button.getAttribute('data-comment-edit-cancel');
      const form = document.querySelector(`[data-comment-edit-form="${commentId}"]`);
      if (form) {
        form.classList.add('is-hidden');
      }
    };
  });

  document.querySelectorAll('[data-comment-edit-form]').forEach((form) => {
    form.onsubmit = async (event) => {
      event.preventDefault();
      const commentId = Number(form.getAttribute('data-comment-edit-form'));
      const textarea = form.querySelector('[name="text"]');
      const text = textarea?.value?.trim() || '';

      try {
        await request(`${window.APP_CONFIG.apiBase}/update-comment.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            comment_id: commentId,
            text,
          }),
        });
        showMessage('Comentario atualizado com sucesso.');
        await loadFeed();
      } catch (error) {
        showMessage(error.message, 'error');
      }
    };
  });

  document.querySelectorAll('[data-comment-delete]').forEach((button) => {
    button.onclick = async () => {
      const commentId = Number(button.getAttribute('data-comment-delete'));
      if (!window.confirm('Deseja excluir este comentario?')) {
        return;
      }

      try {
        await request(`${window.APP_CONFIG.apiBase}/delete-comment.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            comment_id: commentId,
          }),
        });
        showMessage('Comentario excluido com sucesso.');
        await loadFeed();
      } catch (error) {
        showMessage(error.message, 'error');
      }
    };
  });
}

async function loadFeed() {
  try {
    const query = state.memorialKey ? `?memorial_key=${encodeURIComponent(state.memorialKey)}` : '';
    const response = await request(`${window.APP_CONFIG.apiBase}/feed.php${query}`);
    state.posts = response.data.posts || [];
    state.currentUser = response.data.current_user || null;
    state.memorialKey = response.data.memorial_key || state.memorialKey;
    state.memorialExists = !!response.data.memorial_exists;
    syncSessionUI();
    syncMemorialState();
    renderFeed();

    if (!state.memorialExists) {
      showMessage('Memorial invalido ou nao cadastrado.', 'error');
    }
  } catch (error) {
    showMessage(error.message, 'error');
  }
}


async function handleGoogleCredential(response) {
  try {
    const data = await request(`${window.APP_CONFIG.apiBase}/google-login.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ credential: response.credential }),
    });
    state.currentUser = data.data.user;
    syncSessionUI();
    renderFeed();
    showMessage(data.message);
  } catch (error) {
    showMessage(error.message, 'error');
  }
}

let googleInitialized = false;
function renderGoogleButtons() {
  if (!window.APP_CONFIG.googleEnabled || !window.google?.accounts?.id) {
    return;
  }

  if (!googleInitialized) {
    window.google.accounts.id.initialize({
      client_id: window.APP_CONFIG.googleClientId,
      callback: handleGoogleCredential,
      ux_mode: 'popup',
      context: 'signin',
    });
    googleInitialized = true;
  }

  document.querySelectorAll('.js-google-btn').forEach((slot) => {
    if (slot.dataset.rendered === '1') {
      return;
    }

    slot.dataset.rendered = '1';
    window.google.accounts.id.renderButton(slot, {
      type: 'icon',
      shape: 'square',
      theme: 'filled_blue',
      size: 'large',
      width: 48,
    });
  });
}

function syncEditorInput() {
  const html = elements.postEditor.innerHTML.trim();
  elements.postText.value = html;
}

document.querySelectorAll('[data-editor-command]').forEach((button) => {
  button.addEventListener('click', () => {
    const command = button.getAttribute('data-editor-command');
    elements.postEditor.focus();
    document.execCommand(command, false);
    syncEditorInput();
  });
});

document.querySelectorAll('[data-editor-emoji]').forEach((button) => {
  button.addEventListener('click', () => {
    const emoji = button.getAttribute('data-editor-emoji') || '';
    elements.postEditor.focus();
    document.execCommand('insertText', false, emoji);
    syncEditorInput();
  });
});

elements.postImageTrigger.addEventListener('click', () => {
  elements.postImage.click();
});

elements.postEditor.addEventListener('input', syncEditorInput);
elements.postEditor.addEventListener('blur', syncEditorInput);

elements.postImage.addEventListener('change', () => {
  const file = elements.postImage.files?.[0];
  elements.selectedFileName.classList.toggle('is-active', !!file);
  elements.attachmentStatusText.textContent = file ? '1 anexo' : 'Nenhum anexo';
});

elements.postForm.addEventListener('submit', async (event) => {
  event.preventDefault();

  try {
    syncEditorInput();
    const hasText = !!plainTextFromHtml(elements.postText.value);
    const hasImage = !!elements.postImage.files?.length;
    if (!hasText && !hasImage) {
      throw new Error('A postagem precisa ter texto ou imagem.');
    }
    await request(`${window.APP_CONFIG.apiBase}/create-post.php`, {
      method: 'POST',
      body: new FormData(elements.postForm),
    });
    showMessage('Postagem criada com sucesso.');
    elements.postText.value = '';
    elements.postEditor.innerHTML = '';
    elements.postImage.value = '';
    elements.selectedFileName.classList.remove('is-active');
    elements.attachmentStatusText.textContent = 'Nenhum anexo';
    if (!state.currentUser) {
      elements.postAuthorName.value = '';
    }
    await loadFeed();
  } catch (error) {
    showMessage(error.message, 'error');
  }
});


elements.logoutButton.addEventListener('click', async () => {
  try {
    await request(`${window.APP_CONFIG.apiBase}/logout.php`, { method: 'POST' });
    state.currentUser = null;
    syncSessionUI();
    renderFeed();
    showMessage('Sessao encerrada.');
  } catch (error) {
    showMessage(error.message, 'error');
  }
});

elements.lightboxClose.addEventListener('click', closeLightbox);
elements.imageLightbox.addEventListener('click', (event) => {
  if (event.target === elements.imageLightbox) {
    closeLightbox();
  }
});

document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape' && !elements.imageLightbox.classList.contains('is-hidden')) {
    closeLightbox();
  }
});

window.addEventListener('load', () => {
  if (state.isEmbedded) {
    document.body.classList.add('is-embedded');
  }

  syncSessionUI();

  if (!window.APP_CONFIG.googleEnabled) {
    document.querySelectorAll('.google-login-slot').forEach((slot) => {
      slot.innerHTML = '<small class="field-help">Configure o Google Client ID.</small>';
    });
  } else {
    const intervalId = setInterval(() => {
      if (window.google?.accounts?.id) {
        clearInterval(intervalId);
        renderGoogleButtons();
      }
    }, 200);
    setTimeout(() => clearInterval(intervalId), 8000);
  }

  loadFeed();
});
