(function ($) {
  "use strict";

  var $notices = $("#papaya-assist-notices");
  var $table = $("#papaya-assist-documents-table tbody");
  var $fileInput = $("#papaya-assist-file-input");
  var $uploadBtn = $("#papaya-assist-upload-btn");
  var $uploadStatus = $("#papaya-assist-upload-status");
  var $ingestBtn = $("#papaya-assist-ingest-btn");
  var $ingestStatus = $("#papaya-assist-ingest-status");

  function showNotice(message, type) {
    var cls = type === "error" ? "notice-error" : "notice-success";
    $notices.html(
      '<div class="notice ' +
        cls +
        ' is-dismissible"><p>' +
        escHtml(message) +
        "</p></div>"
    );
  }

  function escHtml(str) {
    var div = document.createElement("div");
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }

  function formatSize(bytes) {
    if (bytes === 0) return "0 B";
    var units = ["B", "KB", "MB", "GB"];
    var i = Math.floor(Math.log(bytes) / Math.log(1024));
    return (bytes / Math.pow(1024, i)).toFixed(1) + " " + units[i];
  }

  function validateFile(file) {
    var ext = file.name.split(".").pop().toLowerCase();
    return papayaAssistAdmin.allowedTypes.indexOf(ext) !== -1;
  }

  // Load document list
  function loadDocuments() {
    $table.html('<tr><td colspan="4">Loading...</td></tr>');
    $.post(papayaAssistAdmin.ajaxUrl, {
      action: "papaya_assist_list_documents",
      nonce: papayaAssistAdmin.nonce,
    })
      .done(function (response) {
        if (!response.success) {
          $table.html(
            '<tr><td colspan="4">' +
              escHtml(response.data) +
              "</td></tr>"
          );
          return;
        }

        var docs = response.data.documents || response.data || [];
        if (!Array.isArray(docs) || docs.length === 0) {
          $table.html(
            '<tr><td colspan="4">No documents uploaded yet.</td></tr>'
          );
          return;
        }

        var rows = "";
        docs.forEach(function (doc) {
          var name = doc.name || doc.filename || doc.key || "";
          var size = doc.size ? formatSize(doc.size) : "-";
          var date = doc.last_modified || "-";
          rows +=
            "<tr>" +
            "<td>" +
            escHtml(name) +
            "</td>" +
            "<td>" +
            escHtml(size) +
            "</td>" +
            "<td>" +
            escHtml(date) +
            "</td>" +
            '<td><button class="button button-small papaya-assist-delete-btn" data-key="' +
            escHtml(name) +
            '">Delete</button></td>' +
            "</tr>";
        });
        $table.html(rows);
      })
      .fail(function () {
        $table.html(
          '<tr><td colspan="4">Failed to load documents.</td></tr>'
        );
      });
  }

  // Upload files
  $uploadBtn.on("click", function () {
    var files = $fileInput[0].files;
    if (!files.length) {
      showNotice("Please select at least one file.", "error");
      return;
    }

    // Validate all files first
    for (var i = 0; i < files.length; i++) {
      if (!validateFile(files[i])) {
        showNotice(
          'File "' +
            files[i].name +
            '" is not a supported type. Allowed: ' +
            papayaAssistAdmin.allowedTypes.join(", "),
          "error"
        );
        return;
      }
    }

    $uploadBtn.prop("disabled", true);
    $uploadStatus.text("Uploading...");

    var uploaded = 0;
    var total = files.length;

    function uploadNext(index) {
      if (index >= total) {
        $uploadBtn.prop("disabled", false);
        $uploadStatus.text("");
        showNotice("Uploaded " + uploaded + " file(s) successfully.", "success");
        loadDocuments();
        enableIngest();
        $fileInput.val("");
        return;
      }

      var file = files[index];
      $uploadStatus.text(
        "Uploading " + (index + 1) + " of " + total + "..."
      );

      // Step 1: Get presigned URL from WP backend
      $.post(papayaAssistAdmin.ajaxUrl, {
        action: "papaya_assist_get_upload_url",
        nonce: papayaAssistAdmin.nonce,
        filename: file.name,
        content_type: file.type || "application/octet-stream",
      })
        .done(function (response) {
          if (!response.success) {
            showNotice(
              'Failed to get upload URL for "' + file.name + '": ' + response.data,
              "error"
            );
            uploadNext(index + 1);
            return;
          }

          var uploadUrl = response.data.upload_url;

          // Step 2: PUT file directly to S3
          var xhr = new XMLHttpRequest();
          xhr.open("PUT", uploadUrl, true);
          xhr.setRequestHeader(
            "Content-Type",
            file.type || "application/octet-stream"
          );
          xhr.onload = function () {
            if (xhr.status >= 200 && xhr.status < 300) {
              uploaded++;
            } else {
              showNotice(
                'Failed to upload "' + file.name + '" to storage.',
                "error"
              );
            }
            uploadNext(index + 1);
          };
          xhr.onerror = function () {
            showNotice(
              'Network error uploading "' + file.name + '".',
              "error"
            );
            uploadNext(index + 1);
          };
          xhr.send(file);
        })
        .fail(function () {
          showNotice(
            'Failed to get upload URL for "' + file.name + '".',
            "error"
          );
          uploadNext(index + 1);
        });
    }

    uploadNext(0);
  });

  // Delete document
  $(document).on("click", ".papaya-assist-delete-btn", function () {
    var key = $(this).data("key");
    if (!confirm('Delete "' + key + '"?')) {
      return;
    }

    var $btn = $(this);
    $btn.prop("disabled", true);

    $.post(papayaAssistAdmin.ajaxUrl, {
      action: "papaya_assist_delete_document",
      nonce: papayaAssistAdmin.nonce,
      key: key,
    })
      .done(function (response) {
        if (response.success) {
          showNotice("Document deleted.", "success");
          loadDocuments();
          enableIngest();
        } else {
          showNotice("Delete failed: " + response.data, "error");
          $btn.prop("disabled", false);
        }
      })
      .fail(function () {
        showNotice("Delete request failed.", "error");
        $btn.prop("disabled", false);
      });
  });

  // Ingest documents
  function disableIngest() {
    $ingestBtn.prop("disabled", true);
  }

  function enableIngest() {
    $ingestBtn.prop("disabled", false);
  }

  $ingestBtn.on("click", function () {
    disableIngest();
    $ingestStatus.text("Processing...");

    $.post(papayaAssistAdmin.ajaxUrl, {
      action: "papaya_assist_ingest",
      nonce: papayaAssistAdmin.nonce,
    })
      .done(function (response) {
        $ingestStatus.text("");

        if (response.success) {
          var data = response.data;
          var msg =
            "Processed " +
            (data.files_processed || 0) +
            " file(s), " +
            (data.chunks_ingested || 0) +
            " chunks ingested.";
          showNotice(msg, "success");
          // Stay disabled — re-enabled after upload or delete
        } else {
          showNotice("Ingestion failed: " + response.data, "error");
          enableIngest();
        }
      })
      .fail(function () {
        $ingestStatus.text("");
        showNotice("Ingestion request failed.", "error");
        enableIngest();
      });
  });

  // Initial load
  if ($table.length) {
    loadDocuments();
  }
})(jQuery);
