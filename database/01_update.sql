-- Insert categories
INSERT INTO category (category) VALUES ('Science Fiction'), ('Programming'), ('History'), ('Filipino Literature');

-- Insert books (linking to the categories above)
INSERT INTO books (category_id, title, author, available_copies) VALUES 
(1, 'Dune', 'Frank Herbert', 3),
(2, 'PHP & MySQL Novice to Ninja', 'Kevin Yank', 5),
(3, 'Philippine History', 'Teodoro Agoncillo', 2),
(4, 'Noli Me Tangere', 'Jose Rizal', 0);

INSERT INTO status (status_id, status) VALUES 
(1, 'Borrowed'), 
(2, 'Returned'), 
(3, 'Overdue')
ON DUPLICATE KEY UPDATE status=status;