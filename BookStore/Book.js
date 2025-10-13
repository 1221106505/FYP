const express = require("express");
const fs = require("fs");
const app = express();

app.use(express.json());

// Read all books
app.get("/books", (req, res) => {
  const data = fs.readFileSync("books.json", "utf-8");
  res.send(JSON.parse(data));
});

// Add a new book
app.post("/books", (req, res) => {
  const { title, author, price } = req.body;
  const data = JSON.parse(fs.readFileSync("books.json", "utf-8"));

  const newBook = {
    id: data.length + 1, // auto-id
    title,
    author,
    price
  };

  data.push(newBook);
  fs.writeFileSync("books.json", JSON.stringify(data, null, 2));

  res.send({ message: "Book added", book: newBook });
});

app.listen(3000, () => console.log("Server running at http://localhost:3000"));