jQuery(document).ready(function($) {
  const button = $('<button type="button" class="button">Bild auswählen</button>');
  const input = $('#jhh_staff_avatar_id');

  if (input.length) {
    input.after(button);

    button.on('click', function(e) {
      e.preventDefault();

      const frame = wp.media({
        title: 'Profilbild auswählen',
        button: { text: 'Verwenden' },
        multiple: false
      });

      frame.on('select', function() {
        const attachment = frame.state().get('selection').first().toJSON();
        input.val(attachment.id);
      });

      frame.open();
    });
  }
});
