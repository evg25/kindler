document.addEventListener('DOMContentLoaded', () => {
    // Poems Search Logic for /poems/index.html
    const searchInput = document.getElementById('search-poems');
    if (searchInput) {
        let allPoems = [];
        const poemListUl = document.getElementById('poem-list-ul');
        const noResults = document.getElementById('no-results');

        fetch('/poems/poems.json')
            .then(res => res.json())
            .then(data => {
                allPoems = data;
                renderPoems(allPoems);
            })
            .catch(err => {
                console.error("Error loading poems:", err);
                poemListUl.innerHTML = '<li>Ошибка загрузки стихов.</li>';
            });

        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            const filtered = allPoems.filter(p => p.title.toLowerCase().includes(query));
            renderPoems(filtered);
            
            if (filtered.length === 0) {
                noResults.classList.remove('hidden');
            } else {
                noResults.classList.add('hidden');
            }
        });

        function renderPoems(poems) {
            poemListUl.innerHTML = '';
            poems.forEach(p => {
                const li = document.createElement('li');
                li.innerHTML = `
                    <a href="/poems/${p.id}.html">${p.title}</a>
                    <span class="poem-year">${p.year || ''}</span>
                `;
                poemListUl.appendChild(li);
            });
        }
    }

    // Poem Prev/Next Navigation Logic for /poems/poem-xxx.html
    const poemNav = document.getElementById('poem-nav');
    if (poemNav) {
        // Find current poem ID from URL e.g. /poems/poem-001.html
        const path = window.location.pathname;
        const currentFilename = path.substring(path.lastIndexOf('/') + 1);
        const currentId = currentFilename.replace('.html', '');
        
        if (currentId && currentId !== 'index') {
            fetch('/poems/poems.json')
                .then(res => res.json())
                .then(data => {
                    const currentIndex = data.findIndex(p => p.id === currentId);
                    if (currentIndex !== -1) {
                        const prevLink = document.getElementById('prev-poem');
                        const nextLink = document.getElementById('next-poem');
                        
                        if (currentIndex > 0) {
                            const prevPoem = data[currentIndex - 1];
                            prevLink.href = `/poems/${prevPoem.id}.html`;
                            prevLink.textContent = `← ${prevPoem.title}`;
                            prevLink.classList.remove('hidden');
                        }
                        
                        if (currentIndex < data.length - 1) {
                            const nextPoem = data[currentIndex + 1];
                            nextLink.href = `/poems/${nextPoem.id}.html`;
                            nextLink.textContent = `${nextPoem.title} →`;
                            nextLink.classList.remove('hidden');
                        }
                    }
                })
                .catch(err => console.error("Error loading poems for nav:", err));
        }
    }
});
