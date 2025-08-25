class Asset {
    private $id;
    private $name;
    private $type;
    private $status;

    public function __construct($id, $name, $type, $status) {
        $this->id = $id;
        $this->name = $name;
        $this->type = $type;
        $this->status = $status;
    }

    public function save() {
        // Code to save the asset record to the database
    }

    public function delete() {
        // Code to delete the asset record from the database
    }

    // Getters and setters for the properties can be added here
}