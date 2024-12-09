<?php
require_once __DIR__ . '/../../database/database.class.php';
$conn = (new Database())->connect();

// Fetch all events
$query = $conn->prepare("SELECT * FROM events");
$query->execute();
$events = $query->fetchAll(PDO::FETCH_ASSOC);

// Serve images from the database
if (isset($_GET['event_image_id'])) {
    $eventId = $_GET['event_image_id'];
    $query = $conn->prepare("SELECT event_image FROM events WHERE event_id = :event_id");
    $query->bindParam(':event_id', $eventId, PDO::PARAM_INT);
    $query->execute();
    $event = $query->fetch(PDO::FETCH_ASSOC);

    if ($event && $event['event_image']) {
        // Determine the image's MIME type
        $imageData = base64_decode($event['event_image']);
        $mimeType = 'image/jpeg'; // Adjust based on your image format (jpeg, png, gif, etc.)

        // Send the image to the browser
        header('Content-Type: ' . $mimeType);
        echo $imageData; // Output the image data
    } else {
        // If no image is found, return a default image
        header('HTTP/1.0 404 Not Found');
        echo 'Image not found';
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle adding or editing an event

    if (isset($_POST['event_name'])) {
        $eventName = $_POST['event_name'];
        $description = $_POST['description'];
        $eventDate = $_POST['event_date'];
        $eventTime = $_POST['event_time'];
        $location = $_POST['event_location'];
        $facilitators = $_POST['facilitator'];

        // Handle image upload as base64
        $imageData = null;
        if (!empty($_FILES['image']['name'])) {
            $imageData = base64_encode(file_get_contents($_FILES['image']['tmp_name'])); // Convert the image to base64 string
        }

        if (isset($_POST['action']) && $_POST['action'] === 'edit') {
            $eventId = $_POST['event_id'];
            $query = $conn->prepare("UPDATE events SET event_name = :event_name, event_description = :description, event_date = :event_date, event_time = :event_time, event_location = :location, facilitator = :facilitator, event_image = :image WHERE event_id = :event_id");
            $query->bindParam(':event_id', $eventId, PDO::PARAM_INT);
        } else {
            $query = $conn->prepare("INSERT INTO events (event_name, event_description, event_date, event_time, event_location, facilitator, event_image) VALUES (:event_name, :description, :event_date, :event_time, :location, :facilitator, :image)");
        }

        $query->bindParam(':event_name', $eventName);
        $query->bindParam(':description', $description);
        $query->bindParam(':event_date', $eventDate);
        $query->bindParam(':event_time', $eventTime);
        $query->bindParam(':location', $location);
        $query->bindParam(':facilitator', $facilitators);
        $query->bindParam(':image', $imageData); // Store the base64 encoded image
        $query->execute();

        if (isset($_POST['action']) && $_POST['action'] === 'edit') {
            echo json_encode(['status' => 'success', 'event' => $_POST]);
        } else {
            // Return the new event
            $newEventId = $conn->lastInsertId();
            $newEvent = [
                'event_id' => $newEventId,
                'event_name' => $eventName,
                'description' => $description,
                'event_date' => $eventDate,
                'event_time' => $eventTime,
                'location' => $location,
                'facilitator' => $facilitators,
                'image' => 'events.php?event_image_id=' . $newEventId, // Image URL
            ];
            echo json_encode(['status' => 'success', 'event' => $newEvent]);
        }
        exit();
    }

    if (isset($_GET['event_id'])) {
        $eventId = $_GET['event_id'];
        $query = $conn->prepare("SELECT * FROM events WHERE event_id = :event_id");
        $query->bindParam(':event_id', $eventId, PDO::PARAM_INT);
        $query->execute();
        $event = $query->fetch(PDO::FETCH_ASSOC);

        if ($event) {
            echo json_encode(['status' => 'success', 'event' => $event]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Event not found.']);
        }
        exit();
    }
}

// Ensure $events is populated
if (!$events) {
    $events = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events Management</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="../../../sms/css/style.css">

    <style>
        .card-img-top {
            height: 150px;
            object-fit: cover;
        }

        .modal-img {
            max-height: 200px;
            object-fit: cover;
            width: 100%;
        }

        .card {
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }

        .card:active {
            transform: scale(0.98);
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
        }

        .card:hover {
            cursor: pointer;
        }
    </style>
</head>

<body class="bg-light">
    <div class="container py-5">
        <h1 class="text-center mb-4">Upcoming Events</h1>

        <!-- Button to add a new event -->
        <button class="btn btn-success mb-4" data-bs-toggle="modal" data-bs-target="#addEventModal">Add Event</button>

        <div class="row">
            <?php foreach ($events as $event): ?>
                <div class="col-md-4 mb-4" id="event-card-<?= $event['event_id'] ?>">
                    <div class="card shadow-sm" data-bs-toggle="modal" data-bs-target="#eventDetailsModal" onclick="fetchEventDetails(<?= $event['event_id'] ?>)">
                        <img src="events.php?event_image_id=<?= $event['event_id'] ?>" class="card-img-top" alt="Event Image">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($event['event_name']) ?></h5>
                            <p class="card-text text-truncate"><?= htmlspecialchars($event['event_description'] ?? 'No description available.') ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Event Details Modal -->
    <div class="modal fade" id="eventDetailsModal" tabindex="-1" aria-labelledby="eventDetailsLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
        <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="event-name"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Event Image -->
                    <img id="event-image" class="card-img-top" alt="Event Image">
                    <h5>
                        <p id="event-description"></p>
                    </h5>
                    <p><strong>Date:</strong> <span id="event-date"></span></p>
                    <p><strong>Time:</strong> <span id="event-time"></span></p>
                    <p><strong>Location:</strong> <span id="event-location"></span></p>
                    <p><strong>Facilitator:</strong> <span id="event-facilitator"></span></p>
                </div>
                <div class="modal-footer">
                    <!-- Edit Button -->
                    <button class="btn btn-warning" id="editEventButton" data-bs-toggle="modal" data-bs-target="#editEventModal">Edit</button>
                    <button class="btn btn-sm btn-primary">View Sports</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Event Modal -->
    <div class="modal fade" id="addEventModal" tabindex="-1" aria-labelledby="addEventLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="addEventForm" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addEventLabel">Add Event</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="event_name" class="form-label">Event Name</label>
                            <input type="text" class="form-control" id="event_name" name="event_name">
                        </div>
                        <div class="mb-3">
                            <label for="event_description" class="form-label">Description</label>
                            <textarea class="form-control" id="event_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="event_date" class="form-label">Event Date</label>
                            <input type="date" class="form-control" id="event_date" name="event_date">
                        </div>
                        <div class="mb-3">
                            <label for="event_time" class="form-label">Event Time</label>
                            <input type="time" class="form-control" id="event_time" name="event_time">
                        </div>
                        <div class="mb-3">
                            <label for="event_location" class="form-label">Location</label>
                            <input type="text" class="form-control" id="event_location" name="location">
                        </div>
                        <div class="mb-3">
                            <label for="facilitators" class="form-label">Facilitator(s)</label>
                            <input type="text" class="form-control" id="facilitators" name="facilitators">
                        </div>
                        <div class="mb-3">
                            <label for="image" class="form-label">Image</label>
                            <input type="file" class="form-control" id="image" name="image">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Save Event</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Event Modal -->
    <div class="modal fade" id="editEventModal" tabindex="-1" aria-labelledby="editEventLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="editEventForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editEventLabel">Edit Event</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="edit_event_id" name="event_id">
                        <div class="mb-3">
                            <label for="edit_event_name" class="form-label">Event Name</label>
                            <input type="text" class="form-control" id="edit_event_name" name="event_name">
                        </div>
                        <div class="mb-3">
                            <label for="edit_event_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_event_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_event_date" class="form-label">Event Date</label>
                            <input type="date" class="form-control" id="edit_event_date" name="event_date">
                        </div>
                        <div class="mb-3">
                            <label for="edit_event_time" class="form-label">Event Time</label>
                            <input type="time" class="form-control" id="edit_event_time" name="event_time">
                        </div>
                        <div class="mb-3">
                            <label for="edit_event_location" class="form-label">Location</label>
                            <input type="text" class="form-control" id="edit_event_location" name="location">
                        </div>
                        <div class="mb-3">
                            <label for="edit_event_facilitator" class="form-label">Facilitator(s)</label>
                            <input type="text" class="form-control" id="edit_event_facilitator" name="facilitator">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function fetchEventDetails(eventId) {
            $.ajax({
                url: `../main/roles/admin_/events.php`,
                method: 'GET',
                data: {
                    event_id: eventId
                },
                dataType: 'json',
                success: function(data) {
                    console.log(data);

                    if (data.status === 'success') {
                        const event = data.event;

                        // Update the modal content with dynamic data
                        $('#event-name').text(event.event_name);
                        $('#event-description').text(event.event_description || 'No description available.');
                        $('#event-date').text(event.event_date);
                        $('#event-time').text(event.event_time);
                        $('#event-location').text(event.event_location);
                        $('#event-facilitator').text(event.facilitator);

                        // Update the event image with Base64 encoding
                        $('#event-image').attr('src', `data:image/png;base64,${event.event_image}`);
                    } else {
                        console.error('Error:', data.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error fetching event details:', error);
                }
            });
        }

        document.getElementById('addEventForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('./index.php?page=admin_events', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const newEvent = data.event;
                        const eventCard = `
                        <div class="col-md-4 mb-4" id="event-card-${newEvent.event_id}">
                            <div class="card shadow-sm" onclick="fetchEventDetails(${newEvent.event_id})">
                                <img src="${newEvent.image}" class="card-img-top" alt="Event Image">
                                <div class="card-body">
                                    <h5 class="card-title">${newEvent.event_name}</h5>
                                    <p class="card-text text-truncate">${newEvent.description}</p>
                                </div>
                            </div>
                        </div>
                    `;
                        document.querySelector('.row').insertAdjacentHTML('beforeend', eventCard);
                        document.getElementById('addEventModal').querySelector('.btn-close').click();
                    }
                });
        });

        $('#editEventButton').on('click', function () {
            const eventId = $('#eventDetailsModal').data('event-id');

            $.ajax({
                url: `../main/roles/admin_/events.php`,
                method: 'GET',
                data: { event_id: eventId },
                dataType: 'json',
                success: function (data) {
                    if (data.status === 'success') {
                        const event = data.event;

                        // Populate edit modal fields
                        $('#edit_event_id').val(event.event_id);
                        $('#edit_event_name').val(event.event_name);
                        $('#edit_event_description').val(event.description);
                        $('#edit_event_date').val(event.event_date);
                        $('#edit_event_time').val(event.event_time);
                        $('#edit_event_location').val(event.event_location);
                        $('#edit_event_facilitator').val(event.facilitator);
                    } else {
                        console.error('Error:', data.message);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Error fetching event data for edit:', error);
                },
            });
        });

        document.getElementById('editEventForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'edit');
            fetch('./index.php?page=admin_events', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        document.getElementById(`event-card-${data.event.event_id}`).querySelector('.card-body').innerHTML = `
                            <h5 class="card-title">${data.event.event_name}</h5>
                            <p class="card-text text-truncate">${data.event.description}</p>
                        `;
                        document.getElementById('editEventModal').querySelector('.btn-close').click(); // Close the modal
                    }
                });
        });
    </script>
</body>
</html>

