{{-- Confirmation SweetAlert2 de toute suppression : <form class="js-confirm-delete" data-title="…" data-name="…">.
     À inclure dans @push('scripts') d'un écran de module. Un formulaire d'un écran AJAX ([data-crud-form], CrudAjax)
     est envoyé en arrière-plan après confirmation ; sinon, envoi classique. --}}
<script>
    $(document).off('submit.confirmDelete').on('submit.confirmDelete', '.js-confirm-delete', function (event) {
        event.preventDefault();
        event.stopImmediatePropagation();
        const form = this;
        Swal.fire({
            icon: 'warning',
            title: $(form).data('title') || 'Supprimer ?',
            text: $(form).data('name') + ' sera supprimé(e) (récupérable par un administrateur).',
            showCancelButton: true,
            confirmButtonText: 'Supprimer',
            cancelButtonText: 'Annuler',
        }).then((result) => {
            if (!result.isConfirmed) return;
            if (window.CrudAjax && form.hasAttribute('data-crud-form')) {
                window.CrudAjax.send(form);
            } else {
                form.submit();
            }
        });
    });
</script>
