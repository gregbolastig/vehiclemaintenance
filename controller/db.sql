-- Vehicle Management System Database Schema

-- Create database
CREATE DATABASE VehicleManagementSystem;
USE VehicleManagementSystem;

-- Driver table
CREATE TABLE Driver (
    DriverID INT PRIMARY KEY AUTO_INCREMENT,
    FirstName VARCHAR(50) NOT NULL,
    MiddleName VARCHAR(50),
    LastName VARCHAR(50) NOT NULL,
    EmployeeNo VARCHAR(20) UNIQUE NOT NULL,
    DateOfBirth DATE NOT NULL,
    Age VARCHAR(20) NOT NULL,
    EmailAddress VARCHAR(100) UNIQUE NOT NULL,
    ContactNumber VARCHAR(20) NOT NULL,
    Address TEXT NOT NULL,
    DriverLicenseNumber VARCHAR(30) UNIQUE NOT NULL,
    DriverLicenseExpiration DATE NOT NULL,
    Password VARCHAR(255) NOT NULL,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Supervisor table
CREATE TABLE Supervisor (
    SupervisorID INT PRIMARY KEY AUTO_INCREMENT,
    FirstName VARCHAR(50) NOT NULL,
    MiddleName VARCHAR(50),
    LastName VARCHAR(50) NOT NULL,
    Email VARCHAR(100) UNIQUE NOT NULL,
    ContactNumber VARCHAR(20) NOT NULL,
    Password VARCHAR(255) NOT NULL,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Vehicle table
CREATE TABLE Vehicle (
    VehicleID INT PRIMARY KEY AUTO_INCREMENT,
    PlateNumber VARCHAR(15) UNIQUE NOT NULL,
    Model VARCHAR(50) NOT NULL,
    ChassisNumber VARCHAR(50) NOT NULL,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Odometer Readings table
CREATE TABLE OdometerReadings (
    ReadingID INT PRIMARY KEY AUTO_INCREMENT,
    VehicleID INT NOT NULL,
    OdometerReading DECIMAL(10,2) NOT NULL,
    ReadingDateTime TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (VehicleID) REFERENCES Vehicle(VehicleID) ON DELETE CASCADE
);

-- Route table
CREATE TABLE Route (
    RouteID INT PRIMARY KEY AUTO_INCREMENT,
    DriverID INT NOT NULL,
    VehicleID INT NOT NULL,
    StartOdometer DECIMAL(10,2) NOT NULL,
    StartDateTime TIMESTAMP NOT NULL,
    EndOdometer DECIMAL(10,2),
    EndDateTime TIMESTAMP NULL,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (DriverID) REFERENCES Driver(DriverID) ON DELETE CASCADE,
    FOREIGN KEY (VehicleID) REFERENCES Vehicle(VehicleID) ON DELETE CASCADE
);


-- Vehicle Report Problem table
CREATE TABLE VehicleReportProblem (
    ReportID INT PRIMARY KEY AUTO_INCREMENT,
    Title VARCHAR(100) NOT NULL,
    Description TEXT NOT NULL,
    VehicleID INT NOT NULL,
    OdometerReading DECIMAL(10,2) NOT NULL,
    ReportDateTime TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (VehicleID) REFERENCES Vehicle(VehicleID) ON DELETE CASCADE
);

-- Maintenance History table
CREATE TABLE MaintenanceHistory (
    MaintenanceID INT PRIMARY KEY AUTO_INCREMENT,
    VehicleID INT NOT NULL,
    InstalledParts TEXT NOT NULL,
    InstalledDate DATE NOT NULL,
    PartsSpecifications TEXT,
    MaintenanceDateTime TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (VehicleID) REFERENCES Vehicle(VehicleID) ON DELETE CASCADE
);

-- Create indexes for better performance
CREATE INDEX idx_driver_employee_no ON Driver(EmployeeNo);
CREATE INDEX idx_driver_license ON Driver(DriverLicenseNumber);
CREATE INDEX idx_vehicle_plate ON Vehicle(PlateNumber);
CREATE INDEX idx_odometer_vehicle ON OdometerReadings(VehicleID);
CREATE INDEX idx_route_driver ON Route(DriverID);
CREATE INDEX idx_route_vehicle ON Route(VehicleID);
CREATE INDEX idx_report_vehicle ON VehicleReportProblem(VehicleID);
CREATE INDEX idx_maintenance_vehicle ON MaintenanceHistory(VehicleID);



-- Add Location column to Vehicle table
ALTER TABLE Vehicle 
ADD COLUMN VehicleLocation VARCHAR(100) NOT NULL DEFAULT 'Boracay',
ADD COLUMN RegistrationExpiration VARCHAR(100) NOT NULL;

-- Add SupervisorLocation column to Supervisor table
ALTER TABLE Supervisor 
ADD COLUMN SupervisorLocation VARCHAR(100) NOT NULL DEFAULT 'Boracay';

-- Create index for better performance on location filtering
CREATE INDEX idx_vehicle_location ON Vehicle(VehicleLocation);
CREATE INDEX idx_supervisor_location ON Supervisor(SupervisorLocation);


ALTER TABLE Route 
ADD COLUMN InspectionBattery BOOLEAN NOT NULL DEFAULT FALSE,
ADD COLUMN InspectionLights BOOLEAN NOT NULL DEFAULT FALSE,
ADD COLUMN InspectionOil BOOLEAN NOT NULL DEFAULT FALSE,
ADD COLUMN InspectionWater BOOLEAN NOT NULL DEFAULT FALSE,
ADD COLUMN InspectionBrakes BOOLEAN NOT NULL DEFAULT FALSE,
ADD COLUMN InspectionAir BOOLEAN NOT NULL DEFAULT FALSE,
ADD COLUMN InspectionGas BOOLEAN NOT NULL DEFAULT FALSE,
ADD COLUMN InspectionEngine BOOLEAN NOT NULL DEFAULT FALSE,
ADD COLUMN InspectionTires BOOLEAN NOT NULL DEFAULT FALSE,
ADD COLUMN InspectionSelf BOOLEAN NOT NULL DEFAULT FALSE,
ADD COLUMN InspectionRemarks TEXT;