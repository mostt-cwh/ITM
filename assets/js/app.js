(function () {
  var key = "itops.hiddenCols";
  var inputs = document.querySelectorAll("[data-col-toggle]");

  function readHidden() {
    try {
      var raw = JSON.parse(localStorage.getItem(key) || "[]");
      return Array.isArray(raw) ? raw : [];
    } catch (error) {
      return [];
    }
  }

  function apply(hidden) {
    document.querySelectorAll("[data-col]").forEach(function (cell) {
      cell.hidden = hidden.indexOf(cell.getAttribute("data-col")) !== -1;
    });
  }

  if (inputs.length) {
    var hidden = readHidden();
    inputs.forEach(function (input) {
      input.checked = hidden.indexOf(input.getAttribute("data-col-toggle")) === -1;
    });
    apply(hidden);

    inputs.forEach(function (input) {
      input.addEventListener("change", function () {
        var next = [];
        inputs.forEach(function (item) {
          if (!item.checked) {
            next.push(item.getAttribute("data-col-toggle"));
          }
        });
        try {
          localStorage.setItem(key, JSON.stringify(next));
        } catch (error) {}
        apply(next);
      });
    });
  }

  document.querySelectorAll("[data-auto-submit]").forEach(function (element) {
    element.addEventListener("change", function () {
      if (element.form) {
        element.form.submit();
      }
    });
  });

  document.querySelectorAll("form[data-confirm]").forEach(function (form) {
    form.addEventListener("submit", function (event) {
      if (!window.confirm(form.getAttribute("data-confirm") || "確定繼續？")) {
        event.preventDefault();
      }
    });
  });
})();
