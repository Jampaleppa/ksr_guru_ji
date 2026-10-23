let d = "";
process.stdin
  .on("data", (c) => (d += c))
  .on("end", () => {
    d = d.replace(/^\uFEFF/, "");
    const j = JSON.parse(d);
    console.log(JSON.stringify(j.find((x) => x.id == 2)));
    console.log(
      "total",
      j.length,
      "with image",
      j.filter((x) => x.image).length,
    );
  });
