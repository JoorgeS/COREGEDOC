// Espera que el DOM esté listo
document.addEventListener("DOMContentLoaded", () => {
    console.log("✅ app.js cargado correctamente");

    document.addEventListener("DOMContentLoaded", () => {
    const contentArea = document.getElementById("content-area");

        // Captura clicks en enlaces con la clase load-page
        document.querySelectorAll(".load-page").forEach(link => {
            link.addEventListener("click", function(e) {
                e.preventDefault();
                const page = this.dataset.page;

                fetch(page)
                    .then(res => {
                        if (!res.ok) throw new Error("Error al cargar " + page);
                        return res.text();
                    })
                    .then(html => {
                        contentArea.innerHTML = html;
                    })
                    .catch(err => {
                        contentArea.innerHTML = `<div class="alert alert-danger">${err}</div>`;
                    });
            });
        });
    });

});
