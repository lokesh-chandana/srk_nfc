<?php
session_start();
require __DIR__ . '/vendor/autoload.php';

$client = new MongoDB\Client("mongodb+srv://lokeshchandana96:Sanjay123456@cluster0.kymai.mongodb.net");
$db = $client->SRK;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'search_student' && isset($_POST['mobile_number'])) {
        $mobile = $_POST['mobile_number'];

        if (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
            echo json_encode(['success' => false, 'message' => 'Invalid mobile number format.']);
            exit;
        }

        $studentCollection = $db->student_data;
        $userCollection = $db->users;

        $student = $studentCollection->findOne(['mobile' => $mobile]);
        $existingUser = $userCollection->findOne(['mobile' => $mobile]);

        if ($existingUser) {
            echo json_encode(['success' => false, 'message' => 'User already exists. Please login.']);
        } elseif ($student) {
            echo json_encode(['success' => true, 'student' => [
                'name' => $student['name'],
                'mobile' => $student['mobile'],
                'email' => $student['email'],
                'id_number' => $student['id_number']
            ]]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No student found with this mobile number.']);
        }
        exit;
    }

    if ($action === 'signup' && isset($_POST['username'], $_POST['email'], $_POST['password'], $_POST['mobile'])) {
        $username = $_POST['username'];
        $email = $_POST['email'];
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $mobile = $_POST['mobile'];

        $userCollection = $db->users;

        $existingUser = $userCollection->findOne(['mobile' => $mobile]);

        if ($existingUser) {
            echo json_encode(['success' => false, 'message' => 'User already exists. Please login.']);
            exit;
        }

        $newUser = [
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'mobile' => $mobile
        ];

        $insertResult = $userCollection->insertOne($newUser);

        if ($insertResult->getInsertedCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Signup successful!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Signup failed. Please try again.']);
        }
        exit;
    }

    if ($action === 'login' && isset($_POST['username'], $_POST['password'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];

        if ($username === 'librarian' && $password === 'Librarian123!@#') {
            $_SESSION['user'] = [
                'username' => 'librarian',
                'role' => 'librarian'
            ];
            echo json_encode(['success' => true, 'user' => $_SESSION['user']]);
            exit;
        }

        $userCollection = $db->users;
        $user = $userCollection->findOne(['username' => $username]);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = [
                'username' => $user['username'],
                'email' => $user['email'],
                'mobile' => $user['mobile'],
                'role' => 'student'
            ];
            echo json_encode(['success' => true, 'user' => $_SESSION['user']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
        }
        exit;
    }

    if ($action === 'logout') {
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Logged out successfully.']);
        exit;
    }

    if ($action === 'check_login') {
        if (isset($_SESSION['user'])) {
            echo json_encode(['success' => true, 'user' => $_SESSION['user']]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    }

    if ($action === 'fetch_transactions') {
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'message' => 'Not logged in']);
            exit;
        }

        $email = $_SESSION['user']['email'];
        $studentCollection = $db->student_data;
        $transactionsCollection = $db->transactions;

        $student = $studentCollection->findOne(['email' => $email]);

        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Student not found']);
            exit;
        }

        $transactions = $transactionsCollection->find(['student_id' => (string)$student['_id']])->toArray();

        $formattedTransactions = array_map(function ($transaction) {
            return [
                'book_id' => $transaction['book_id'] ?? '',
                'book_name' => $transaction['book_name'] ?? '',
                'date' => $transaction['date'] ?? '',
                'time' => $transaction['time'] ?? '',
                'status' => $transaction['status'] ?? 'Issued'
            ];
        }, $transactions);

        echo json_encode(['success' => true, 'transactions' => $formattedTransactions]);
        exit;
    }

    if ($action === 'fetch_profile') {
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'message' => 'User not logged in']);
            exit;
        }

        $email = $_SESSION['user']['email'];
        $studentCollection = $db->student_data;

        $student = $studentCollection->findOne(['email' => $email]);

        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Student not found']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'student' => [
                'username' => $student['name'] ?? '',
                'email' => $student['email'] ?? '',
                'mobile' => $student['mobile'] ?? '',
                'id_number' => $student['id_number'] ?? ''
            ]
        ]);
        exit;
    }

    if ($action === 'update_profile') {
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'message' => 'User not logged in']);
            exit;
        }

        $email = $_SESSION['user']['email'];
        $username = $_POST['username'];
        $mobile = $_POST['mobile'];

        $studentCollection = $db->student_data;

        $updateResult = $studentCollection->updateOne(
            ['email' => $email],
            ['$set' => ['name' => $username, 'mobile' => $mobile]]
        );

        if ($updateResult->getModifiedCount() > 0) {
            $_SESSION['user']['username'] = $username;
            $_SESSION['user']['mobile'] = $mobile;
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No changes made or update failed.']);
        }
        exit;
    }

    if ($action === 'fetch_all_transactions') {
        // Check if user is logged in
        if (!isset($_SESSION['user'])) {
            echo json_encode([
                'success' => false, 
                'message' => 'Not logged in'
            ]);
            exit;
        }

        try {
            $transactionsCollection = $db->transactions;
            $studentsCollection = $db->student_data; // Reference to student_data collection
            
            // Fetch all transactions
            $transactions = $transactionsCollection->find()->toArray();
            
            // Format transactions and replace student_id with student name
            $formattedTransactions = array_map(function ($transaction) use ($studentsCollection) {
                $studentName = 'Unknown Student'; // Default value
                $studentId = $transaction['student_id'] ?? null;

                // Check if student_id exists and handle it as an ObjectId
                if ($studentId) {
                    // If student_id is already an ObjectId, use it directly
                    if ($studentId instanceof MongoDB\BSON\ObjectId) {
                        $student = $studentsCollection->findOne(['_id' => $studentId]);
                    } else {
                        // If student_id is a string, convert it to ObjectId
                        try {
                            $objectId = new MongoDB\BSON\ObjectId((string)$studentId);
                            $student = $studentsCollection->findOne(['_id' => $objectId]);
                        } catch (Exception $e) {
                            $student = null; // Invalid ObjectId string
                        }
                    }
                    
                    // If student is found, use their name
                    if ($student) {
                        $studentName = $student['name'] ?? 'Unknown Student';
                    }
                }

                return [
                    'book_id' => $transaction['book_id'] ?? 'N/A',
                    'book_name' => $transaction['book_name'] ?? 'N/A',
                    'student_name' => $studentName,
                    'date' => $transaction['date'] ?? 'N/A',
                    'time' => $transaction['time'] ?? 'N/A',
                    'status' => $transaction['status'] ?? 'Issued'
                ];
            }, $transactions);

            // Return successful response
            echo json_encode([
                'success' => true,
                'transactions' => $formattedTransactions
            ]);
        } catch (Exception $e) {
            // Handle any database or processing errors
            echo json_encode([
                'success' => false,
                'message' => 'Error fetching transactions: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    if ($action === 'fetch_all_books') {
        // Check if user is logged in
        if (!isset($_SESSION['user'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Not logged in'
            ]);
            exit;
        }

        try {
            $booksCollection = $db->books; // Reference to books collection
            
            // Fetch all books
            $books = $booksCollection->find()->toArray();
            
            // Format books data
            $formattedBooks = array_map(function ($book) {
                return [
                    'book_id' => $book['Book ID'] ?? 'N/A',
                    'book_name' => $book['Book Name'] ?? 'N/A',
                    'category' => $book['Category'] ?? 'N/A',
                    'price' => $book['Price'] ?? 0.0,
                    'author' => $book['Author'] ?? 'Unknown Author'
                ];
            }, $books);

            // Return successful response
            echo json_encode([
                'success' => true,
                'books' => $formattedBooks
            ]);
        } catch (Exception $e) {
            // Handle any database or processing errors
            echo json_encode([
                'success' => false,
                'message' => 'Error fetching books: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    if ($action === 'fetch_all_students') {
        // Check if user is logged in
        if (!isset($_SESSION['user'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Not logged in'
            ]);
            exit;
        }

        try {
            $studentsCollection = $db->student_data; // Reference to student_data collection
            
            // Fetch all students
            $students = $studentsCollection->find()->toArray();
            
            // Format students data
            $formattedStudents = array_map(function ($student) {
                return [
                    'student_id' => (string) ($student['_id'] ?? 'N/A'), // Convert ObjectId to string
                    'name' => $student['name'] ?? 'N/A',
                    'email' => $student['email'] ?? 'N/A',
                    'mobile' => $student['mobile'] ?? 'N/A'
                ];
            }, $students);

            // Return successful response
            echo json_encode([
                'success' => true,
                'students' => $formattedStudents
            ]);
        } catch (Exception $e) {
            // Handle any database or processing errors
            echo json_encode([
                'success' => false,
                'message' => 'Error fetching students: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    if ($action === 'add_transaction') {
    if (!isset($_SESSION['user'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Not logged in'
        ]);
        exit;
    }

    try {
        $transactionsCollection = $db->transactions;
        $booksCollection = $db->books;
        $studentsCollection = $db->student_data;

        $bookId = $_POST['book_id'] ?? '';
        $studentId = $_POST['student_id'] ?? '';
        $date = $_POST['date'] ?? '';
        $time = $_POST['time'] ?? '';
        $status = $_POST['status'] ?? 'Issued';

        // Validate inputs
        if (!$bookId || !$studentId || !$date || !$time || !$status) {
            echo json_encode([
                'success' => false,
                'message' => 'All fields are required'
            ]);
            exit;
        }

        // Check if book exists
        $book = $booksCollection->findOne(['Book ID' => $bookId]);
        if (!$book) {
            echo json_encode([
                'success' => false,
                'message' => 'Book not found'
            ]);
            exit;
        }

        // Check if student exists and convert student_id to ObjectId
        $studentObjectId = new MongoDB\BSON\ObjectId($studentId);
        $student = $studentsCollection->findOne(['_id' => $studentObjectId]);
        if (!$student) {
            echo json_encode([
                'success' => false,
                'message' => 'Student not found'
            ]);
            exit;
        }

        // Insert new transaction
        $result = $transactionsCollection->insertOne([
            'book_id' => $bookId,
            'book_name' => $book['Book Name'] ?? 'N/A',
            'student_id' => $studentObjectId,
            'date' => $date,
            'time' => $time,
            'status' => $status
        ]);

        if ($result->getInsertedCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Transaction added successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to add transaction'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error adding transaction: ' . $e->getMessage()
        ]);
    }
    exit;
}

    echo json_encode(['success' => false, 'message' => 'Invalid action or missing parameters.']);
}
?>
