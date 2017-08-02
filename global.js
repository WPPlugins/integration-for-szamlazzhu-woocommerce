jQuery(document).ready(function($) {
  $('#wc_szamlazz_generate').click(function(e) {
    e.preventDefault();
    var r = confirm("Biztosan létrehozod a számlát?");
    if (r != true) {
      return false;
    }
    var nonce = $(this).data('nonce');
    var order = $(this).data('order');
    var button = $('#wc-szamlazz-generate-button');
    var note = $('#wc_szamlazz_invoice_note').val();
    var deadline = $('#wc_szamlazz_invoice_deadline').val();
    var completed = $('#wc_szamlazz_invoice_completed').val();
    var request = $('#wc_szamlazz_invoice_request').is(':checked');
    if (request) {
      request = 'on';
    } else {
      request = 'off';
    }

    var data = {
      action: 'wc_szamlazz_generate_invoice',
      nonce: nonce,
      order: order,
      note: note,
      deadline: deadline,
      completed: completed,
      request: request
    };

    button.block({
      message: null,
      overlayCSS: {
        background: '#fff url(' + wc_szamlazz_params.loading + ') no-repeat center',
        backgroundSize: '16px 16px',
        opacity: 0.6
      }
    });

    $.post(ajaxurl, data, function(response) {
      //Remove old messages
      $('.wc-szamlazz-message').remove();

      var responseText = response;

      //Generate the error/success messages
      if (responseText.data.error) {
        button.before('<div class="wc-szamlazz-error error wc-szamlazz-message"></div>');
      } else {
        button.before('<div class="wc-szamlazz-success updated wc-szamlazz-message"></div>');
      }

      //Get the error messages
      var ul = $('<ul>');
      $.each(responseText.data.messages, function(i, value) {
        var li = $('<li>')
        li.append(value);
        ul.append(li);
      });
      $('.wc-szamlazz-message').append(ul);

      //If success, hide the button
      if (!responseText.data.error) {
        button.slideUp();
        button.before(responseText.data.link);
      }

      button.unblock();


    });
  });

  $('#wc_szamlazz_options').click(function() {
    $('#wc_szamlazz_options_form').slideToggle();
    return false;
  });

  $('#wc_szamlazz_already').click(function(e) {
    e.preventDefault();
    var note = prompt("Számlakészítés kikapcsolása. Mi az indok?", "Ehhez a rendeléshez nem kell számla.");
    if (!note) {
      return false;
    }

    var nonce = $(this).data('nonce');
    var order = $(this).data('order');
    var button = $('#wc-szamlazz-generate-button');

    var data = {
      action: 'wc_szamlazz_already',
      nonce: nonce,
      order: order,
      note: note
    };

    button.block({
      message: null,
      overlayCSS: {
        background: '#fff url(' + wc_szamlazz_params.loading + ') no-repeat center',
        backgroundSize: '16px 16px',
        opacity: 0.6
      }
    });

    $.post(ajaxurl, data, function(response) {
      //Remove old messages
      $('.wc-szamlazz-message').remove();

      var responseText = response;

      //Generate the error/success messages
      if (responseText.data.error) {
        button.before('<div class="wc-szamlazz-error error wc-szamlazz-message"></div>');
      } else {
        button.before('<div class="wc-szamlazz-success updated wc-szamlazz-message"></div>');
      }

      //Get the error messages
      var ul = $('<ul>');
      $.each(responseText.data.messages, function(i, value) {
        var li = $('<li>')
        li.append(value);
        ul.append(li);
      });
      $('.wc-szamlazz-message').append(ul);

      //If success, hide the button
      if (!responseText.data.error) {
        button.slideUp();
        button.before(responseText.data.link);
      }

      button.unblock();


    });
  });

  $('#wc_szamlazz_already_back').click(function(e) {
    e.preventDefault();
    var r = confirm("Biztosan visszakapcsolod a számlakészítés ennél a rendelésnél?");
    if (r != true) {
      return false;
    }

    var nonce = $(this).data('nonce');
    var order = $(this).data('order');
    var button = $('#wc-szamlazz-generate-button');

    var data = {
      action: 'wc_szamlazz_already_back',
      nonce: nonce,
      order: order
    };

    $('#szamlazz_already_div').block({
      message: null,
      overlayCSS: {
        background: '#fff url(' + wc_szamlazz_params.loading + ') no-repeat center',
        backgroundSize: '16px 16px',
        opacity: 0.6
      }
    });

    $.post(ajaxurl, data, function(response) {
      //Remove old messages
      $('.wc-szamlazz-message').remove();

      var responseText = response;

      //Generate the error/success messages
      if (responseText.data.error) {
        button.before('<div class="wc-szamlazz-error error wc-szamlazz-message"></div>');
      } else {
        button.before('<div class="wc-szamlazz-success updated wc-szamlazz-message"></div>');
      }

      //Get the error messages
      var ul = $('<ul>');
      $.each(responseText.data.messages, function(i, value) {
        var li = $('<li>')
        li.append(value);
        ul.append(li);
      });
      $('.wc-szamlazz-message').append(ul);

      //If success, show the button
      if (!responseText.data.error) {
        button.slideDown();
      }

      $('#szamlazz_already_div').unblock().slideUp();


    });
  });

  //Teljesítettnek jelölés
  $('#wc_szamlazz_generate_complete').click(function(e) {
    e.preventDefault();
    var r = confirm("Biztosan teljesítve lett?");
    if (r != true) {
      return false;
    }

    var nonce = $(this).data('nonce');
    var order = $(this).data('order');
    var form = $('#wc-szamlazz-generate-button');
    var button = $(this);

    var data = {
      action: 'wc_szamlazz_complete',
      nonce: nonce,
      order: order
    };

    form.block({
      message: null,
      overlayCSS: {
        background: '#fff url(' + wc_szamlazz_params.loading + ') no-repeat center',
        backgroundSize: '16px 16px',
        opacity: 0.6
      }
    });

    $.post(ajaxurl, data, function(response) {
      //Remove old messages
      $('.wc-szamlazz-message').remove();

      var responseText = response;

      //Generate the error/success messages
      if (responseText.data.error) {
        form.before('<div class="wc-szamlazz-error error wc-szamlazz-message"></div>');
      } else {
        form.before('<div class="wc-szamlazz-success updated wc-szamlazz-message"></div>');
      }

      //Get the error messages
      var ul = $('<ul>');
      $.each(responseText.data.messages, function(i, value) {
        var li = $('<li>')
        li.append(value);
        ul.append(li);
      });
      $('.wc-szamlazz-message').append(ul);

      //If success, hide the button
      if (!responseText.data.error) {
        button.slideUp();
        button.before(responseText.data.link);
      }

      form.unblock();


    });
  });

  //Teljesítettnek jelölés
  $('#wc_szamlazz_generate_sztorno').click(function(e) {
    e.preventDefault();
    var r = confirm("Biztosan sztornózva lesz?");
    if (r != true) {
      return false;
    }

    var nonce = $(this).data('nonce');
    var order = $(this).data('order');
    var form = $('#wc-szamlazz-generate-button');
    var button = $(this);

    var data = {
      action: 'wc_szamlazz_sztorno',
      nonce: nonce,
      order: order
    };

    form.block({
      message: null,
      overlayCSS: {
        background: '#fff url(' + wc_szamlazz_params.loading + ') no-repeat center',
        backgroundSize: '16px 16px',
        opacity: 0.6
      }
    });

    $.post(ajaxurl, data, function(response) {
      //Remove old messages
      $('.wc-szamlazz-message').remove();

      var responseText = response;

      //Generate the error/success messages
      if (responseText.data.error) {
        form.before('<div class="wc-szamlazz-error error wc-szamlazz-message"></div>');
      } else {
        form.before('<div class="wc-szamlazz-success updated wc-szamlazz-message"></div>');
      }

      //Get the error messages
      var ul = $('<ul>');
      $.each(responseText.data.messages, function(i, value) {
        var li = $('<li>')
        li.append(value);
        ul.append(li);
      });
      $('.wc-szamlazz-message').append(ul);

      //If success, hide the button
      if (!responseText.data.error) {
        button.slideUp();
        button.before(responseText.data.link);
        $('#wc-szamlazz-generated-data').slideUp();
      }

      form.unblock();

    });
  });

});
