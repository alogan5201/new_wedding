function copy() {
  var copyText = document.getElementById("div-2078").innerhtml;
  copyText.select();
  copyText.setSelectionRange(0, 99999);
  document.execCommand("copy");
  $("#copied-success").fadeIn(800);
  $("#copied-success").fadeOut(800);
}
$(document).on(
  "page:afterin",
  '.page[data-name="vendor_detail"]',
  function (e, page) {
    $("#copy-btn").on("click", function (e) {
      e.preventDefault();
      let el = this.previousElementSibling;
      if (el.value !== undefined) {
        copyText(el.value);
      } else {
        copyText(el.textContent);
      }
    });
  }
);

copyText = function (textToCopy) {
  this.copied = false;

  // Create textarea element
  const textarea = document.createElement("textarea");

  // Set the value of the text
  textarea.value = textToCopy;

  // Make sure we cant change the text of the textarea
  textarea.setAttribute("readonly", "");

  // Hide the textarea off the screnn
  textarea.style.position = "absolute";
  textarea.style.left = "-9999px";

  // Add the textarea to the page
  document.body.appendChild(textarea);

  // Copy the textarea
  textarea.select();

  try {
    var successful = document.execCommand("copy");
    this.copied = true;
  } catch (err) {
    this.copied = false;
  }

  textarea.remove();
};

copyById = function (id) {
  let text = document.getElementById(id);
  copyText(text.value);
};

copyPreviousSibling = function (curr) {
  let el = curr.previousElementSibling;
  if (el.value !== undefined) {
    copyText(el.value);
  } else {
    copyText(el.textContent);
  }
};

$(document).on(
  "page:afterin",
  '.page[data-name="vendors"]',
  function (e, page) {
    const fruits =
      "Test Vendor Bob Banana Melon Orange Peach Pear Pineapple".split(" ");

    let autocompleteDropdownAll;

    var getInitials = function (name) {
      var parts = name.split(" ");
      var initials = "";
      for (var i = 0; i < parts.length; i++) {
        console.log(parts.length);
        if (parts[i].length > 0 && parts[i] !== "") {
          initials += parts[i][0];
        }
      }
      let lastCharacter = initials.length - 1;
      let firstInitial = initials.charAt(0);
      let lastInitial = initials.charAt(lastCharacter);
      let twoInitials = firstInitial + lastInitial;
      console.log(firstInitial);
      return twoInitials;
    };

    let vendorList = document.querySelectorAll(".initials");
    function vendorInitialsAvatar() {
      for (let i = 0; i < vendorList.length; i++) {
        let name = vendorList[i].innerHTML;
        let initials = getInitials(name);
        vendorList[i].innerHTML = initials;
      }
    }
    //vendorInitialsAvatar();

    $("#lastWord").html(function () {
      var text = $(this).text().trim().split(" ");
      var last = text.pop();
      return (
        text.join(" ") +
        (text.length > 0 ? " <span class='red'>" + last + "</span>" : last)
      );
    });

    $("#firstWord").html(function () {
      var text = $(this).text().trim().split(" ");
      var first = text.shift();
      return (
        (text.length > 0 ? "<span class='red'>" + first + "</span> " : first) +
        text.join(" ")
      );
    });
    // create searchbar
    var searchbar = app.searchbar.create({
      el: ".searchbar-demo",
      searchContainer: ".dbexpress-virtual-list",
      searchIn:
        ".item-content .item-inner .item-title-row .item-title .dbexpress-field",
      backdrop: false,
      on: {
        search(sb, query, previousQuery) {
          console.log(query, previousQuery);
        },
      },
    });

    $(".filter-btn").on("click", function (e) {
      e.preventDefault();
      if (!autocompleteDropdownAll) {
        console.log("no autocomplete yet");
        autocompleteDropdownAll = app.autocomplete.create({
          inputEl: ".autocomplete-input",
          openIn: "dropdown",

          //autoFocus: true,
          source: function (query, render) {
            var results = [];
            // Find matched items
            for (var i = 0; i < fruits.length; i++) {
              if (fruits[i].toLowerCase().indexOf(query.toLowerCase()) >= 0)
                results.push(fruits[i]);
            }
            // Render items by passing array with result items
            render(results);
          },
        });
        autocompleteDropdownAll.open();
      } else if (autocompleteDropdownAll.opened) {
        autocompleteDropdownAll.close();
        autocompleteDropdownAll.destroy();

        // console.log(`Autcomplete status ${autocompleteDropdownAll.opened}`);
      } else {
        autocompleteDropdownAll = app.autocomplete.create({
          inputEl: ".autocomplete-input",
          openIn: "dropdown",
          //closeOnSelect: true,
          //autoFocus: true,
          source: function (query, render) {
            var results = [];
            // Find matched items
            for (var i = 0; i < fruits.length; i++) {
              if (fruits[i].toLowerCase().indexOf(query.toLowerCase()) >= 0)
                results.push(fruits[i]);
            }
            // Render items by passing array with result items
            render(results);
          },
        });
        autocompleteDropdownAll.open();
      }
    });
    $(".item-inner").on("click", function () {
      console.log("autocompleteDropdownAll detected");
      app.autocomplete.close(".autocomplete-input");
      app.autocomplete.destroy(".autocomplete-input");
    });

    $(".disable-btn").on("click", function (e) {
      e.preventDefault();
      //let searchbar = app.searchbar.get(".searchbar-demo");
      if (autocompleteDropdownAll) {
        autocompleteDropdownAll.close();
        app.autocomplete.destroy(".autocomplete-input");
        searchbar.disable();
      } else if (!autocompleteDropdownAll) {
        app.searchbar.disable(".searchbar-demo");
      }
    });
  }
);
