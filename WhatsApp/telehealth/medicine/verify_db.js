const { DatabaseSync } = require("node:sqlite");
const d = new DatabaseSync(__dirname + "/medicines.sqlite", { readOnly: true });
console.log(
  "tables:",
  JSON.stringify(
    d.prepare("SELECT name FROM sqlite_master WHERE type='table'").all(),
  ),
);
console.log(
  "rows:",
  d.prepare("SELECT COUNT(*) c FROM medicine_catalogue").get().c,
);
console.log(
  "forms:",
  JSON.stringify(
    d
      .prepare(
        "SELECT dosage_form, COUNT(*) c FROM medicine_catalogue GROUP BY dosage_form ORDER BY c DESC",
      )
      .all(),
  ),
);
console.log(
  "sample:",
  JSON.stringify(
    d
      .prepare(
        "SELECT sl_no, medicine_name, strength, dosage_form FROM medicine_catalogue LIMIT 3",
      )
      .all(),
  ),
);
d.close();
console.log("DB OK");
