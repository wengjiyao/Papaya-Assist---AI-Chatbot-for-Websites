(function ($) {
  "use strict";

  var $notices = $("#bitesize-auth-notices");
  var $connected = $("#bitesize-connected");
  var $notConnected = $("#bitesize-not-connected");

  function showNotice(message, type) {
    var cls = type === "error" ? "notice-error" : "notice-success";
    $notices.html(
      '<div class="notice ' + cls + ' is-dismissible"><p>' +
        escHtml(message) +
        "</p></div>"
    );
  }

  function escHtml(str) {
    var div = document.createElement("div");
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }

  // Open popup to weng.ca/auth/chatbot
  $(document).on("click", "#bitesize-connect-btn", function () {
    var url =
      bitesizeSettings.authUrl +
      "?tenant_id=" +
      encodeURIComponent(bitesizeSettings.tenantId);

    var w = 450;
    var h = 600;
    var left = (screen.width - w) / 2;
    var top = (screen.height - h) / 2;

    window.open(
      url,
      "bitesize-auth",
      "width=" + w + ",height=" + h + ",left=" + left + ",top=" + top
    );
  });

  // Listen for postMessage from popup
  window.addEventListener("message", function (event) {
    // Verify origin
    if (event.origin !== "https://weng.ca") return;
    if (!event.data || event.data.type !== "bitesize-auth") return;

    var data = event.data;

    // Save credentials to WordPress via AJAX
    $.post(bitesizeSettings.ajaxUrl, {
      action: "bitesize_save_credentials",
      nonce: bitesizeSettings.nonce,
      api_key: data.api_key,
      tenant_id: data.tenant_id,
      email: data.email,
    })
      .done(function (response) {
        if (response.success) {
          // Update UI to connected state
          $("#bitesize-connected-email").text(data.email);
          $connected.show();
          $notConnected.hide();
          showNotice("Account connected successfully!", "success");
        } else {
          showNotice("Failed to save credentials.", "error");
        }
      })
      .fail(function () {
        showNotice("Failed to save credentials.", "error");
      });
  });

  // Change password
  $(document).on("click", "#bitesize-change-password-btn", function () {
    var password = $("#bitesize-new-password").val();
    if (!password || password.length < 8) {
      showNotice("Password must be at least 8 characters.", "error");
      return;
    }

    var $btn = $(this);
    $btn.prop("disabled", true);

    $.post(bitesizeSettings.ajaxUrl, {
      action: "bitesize_change_password",
      nonce: bitesizeSettings.nonce,
      password: password,
    })
      .done(function (response) {
        if (response.success) {
          $("#bitesize-new-password").val("");
          showNotice("Password changed successfully!", "success");
        } else {
          showNotice(response.data || "Failed to change password.", "error");
        }
      })
      .fail(function () {
        showNotice("Failed to change password.", "error");
      })
      .always(function () {
        $btn.prop("disabled", false);
      });
  });

  // Fetch and display usage info
  function loadUsage() {
    if (!$connected.is(":visible")) return;
    $.post(bitesizeSettings.ajaxUrl, {
      action: "bitesize_get_usage",
      nonce: bitesizeSettings.nonce,
    }).done(function (response) {
      if (response.success && response.data) {
        var d = response.data;
        var tier = d.tier.charAt(0).toUpperCase() + d.tier.slice(1);
        var docLimit =
          d.document_limit >= 9007199254740991 ? "Unlimited" : d.document_limit;
        $("#bitesize-usage-text").text(
          tier +
            " tier: " +
            d.message_count +
            "/" +
            d.message_limit +
            " messages this month, " +
            d.document_count +
            "/" +
            docLimit +
            " documents"
        );
      } else {
        $("#bitesize-usage-text").text("Unable to load usage info.");
      }
    }).fail(function () {
      $("#bitesize-usage-text").text("Unable to load usage info.");
    });
  }

  loadUsage();

  // Upgrade Plan
  $(document).on("click", "#bitesize-upgrade-btn", function () {
    var $btn = $(this);
    $btn.prop("disabled", true).text("Loading...");

    // Open window immediately (in click context) to avoid popup blocker
    var upgradeWindow = window.open("about:blank", "_blank");

    $.post(bitesizeSettings.ajaxUrl, {
      action: "bitesize_get_upgrade_url",
      nonce: bitesizeSettings.nonce,
    })
      .done(function (response) {
        if (response.success && response.data && response.data.url) {
          upgradeWindow.location.href = response.data.url;
        } else {
          upgradeWindow.close();
          showNotice(response.data || "Failed to get upgrade link.", "error");
        }
      })
      .fail(function () {
        upgradeWindow.close();
        showNotice("Failed to get upgrade link.", "error");
      })
      .always(function () {
        $btn.prop("disabled", false).text("Upgrade Plan");
      });
  });

  // Disconnect
  $(document).on("click", "#bitesize-disconnect-btn", function () {
    if (
      !confirm(
        "Disconnect your account? The chatbot will stop working until you connect again."
      )
    ) {
      return;
    }

    $.post(bitesizeSettings.ajaxUrl, {
      action: "bitesize_disconnect",
      nonce: bitesizeSettings.nonce,
    }).done(function (response) {
      if (response.success) {
        $connected.hide();
        $notConnected.show();
        showNotice("Account disconnected.", "success");
      }
    });
  });
})(jQuery);
