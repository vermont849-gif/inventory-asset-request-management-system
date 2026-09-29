-- IARMS - Inventory and Asset Request Management System
-- DEVELOPMENT-ONLY schema reset for a local demonstration.
-- This script destroys and recreates the iarms_db database.
-- Never run it against production or a database whose data you need to keep.

DROP DATABASE IF EXISTS iarms_db;
CREATE DATABASE iarms_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE iarms_db;

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Department  (ManagerID FK added after Employee exists — see bottom)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS Department (
    DepartmentID   INT AUTO_INCREMENT PRIMARY KEY,
    DepartmentName VARCHAR(100) NOT NULL,
    ManagerID      INT NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Employee
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS Employee (
    EmployeeID   INT AUTO_INCREMENT PRIMARY KEY,
    FullName     VARCHAR(100) NOT NULL,
    JobTitle     VARCHAR(100) NOT NULL,
    Email        VARCHAR(100) NOT NULL UNIQUE,
    Phone        VARCHAR(20),
    DepartmentID INT,
    Status       ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    CONSTRAINT fk_employee_department FOREIGN KEY (DepartmentID)
        REFERENCES Department(DepartmentID) ON DELETE SET NULL
) ENGINE=InnoDB;

ALTER TABLE Department
    ADD CONSTRAINT fk_department_manager FOREIGN KEY (ManagerID)
        REFERENCES Employee(EmployeeID) ON DELETE SET NULL;

-- ---------------------------------------------------------------------
-- Role / Permission / RolePermission  (RBAC)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS Role (
    RoleID   INT AUTO_INCREMENT PRIMARY KEY,
    RoleName VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Permission (
    PermissionID   INT AUTO_INCREMENT PRIMARY KEY,
    PermissionName VARCHAR(100) NOT NULL,
    Module         VARCHAR(50) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS RolePermission (
    RoleID       INT NOT NULL,
    PermissionID INT NOT NULL,
    PRIMARY KEY (RoleID, PermissionID),
    CONSTRAINT fk_rp_role FOREIGN KEY (RoleID) REFERENCES Role(RoleID) ON DELETE CASCADE,
    CONSTRAINT fk_rp_permission FOREIGN KEY (PermissionID) REFERENCES Permission(PermissionID) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- UserAccount
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS UserAccount (
    UserID       INT AUTO_INCREMENT PRIMARY KEY,
    Username     VARCHAR(50) NOT NULL UNIQUE,
    PasswordHash VARCHAR(255) NOT NULL,
    RoleID       INT NOT NULL,
    EmployeeID   INT NOT NULL,
    Status       ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    CreatedAt    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_role FOREIGN KEY (RoleID) REFERENCES Role(RoleID),
    CONSTRAINT fk_user_employee FOREIGN KEY (EmployeeID) REFERENCES Employee(EmployeeID)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Category / Supplier / Asset
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS Category (
    CategoryID   INT AUTO_INCREMENT PRIMARY KEY,
    CategoryName VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Supplier (
    SupplierID    INT AUTO_INCREMENT PRIMARY KEY,
    Name          VARCHAR(100) NOT NULL,
    ContactPerson VARCHAR(100),
    Phone         VARCHAR(20),
    Email         VARCHAR(100),
    Address       TEXT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Asset (
    AssetID       INT AUTO_INCREMENT PRIMARY KEY,
    Name          VARCHAR(100) NOT NULL,
    Description   TEXT,
    CategoryID    INT,
    UnitOfMeasure VARCHAR(30),
    CurrentQty    INT NOT NULL DEFAULT 0,
    MinStockLevel INT NOT NULL DEFAULT 0,
    UnitPrice     DECIMAL(10,2) DEFAULT 0,
    SupplierID    INT,
    Status        ENUM('Available','Unavailable') NOT NULL DEFAULT 'Available',
    CONSTRAINT fk_asset_category FOREIGN KEY (CategoryID) REFERENCES Category(CategoryID) ON DELETE SET NULL,
    CONSTRAINT fk_asset_supplier FOREIGN KEY (SupplierID) REFERENCES Supplier(SupplierID) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Request / RequestDetail
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS Request (
    RequestID     INT AUTO_INCREMENT PRIMARY KEY,
    EmployeeID    INT NOT NULL,
    RequestType   ENUM('New','Maintenance','Disposal') NOT NULL,
    Status        ENUM('Pending','Approved','Rejected','Returned','Fulfilled') NOT NULL DEFAULT 'Pending',
    SubmittedDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    RequiredByDate DATE NULL,
    Reason        TEXT,
    ReviewComment TEXT,
    ReviewedBy    INT NULL,
    ReviewedDate  DATETIME NULL,
    CONSTRAINT fk_request_employee FOREIGN KEY (EmployeeID) REFERENCES Employee(EmployeeID),
    CONSTRAINT fk_request_reviewer FOREIGN KEY (ReviewedBy) REFERENCES UserAccount(UserID) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS RequestDetail (
    DetailID  INT AUTO_INCREMENT PRIMARY KEY,
    RequestID INT NOT NULL,
    AssetID   INT NOT NULL,
    Quantity  INT NOT NULL DEFAULT 1,
    CONSTRAINT fk_detail_request FOREIGN KEY (RequestID) REFERENCES Request(RequestID) ON DELETE CASCADE,
    CONSTRAINT fk_detail_asset FOREIGN KEY (AssetID) REFERENCES Asset(AssetID)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- IssuedAsset
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS IssuedAsset (
    IssueID            INT AUTO_INCREMENT PRIMARY KEY,
    RequestID          INT NOT NULL,
    EmployeeID         INT NOT NULL,
    AssetID            INT NOT NULL,
    QuantityIssued     INT NOT NULL,
    IssueDate          DATE NOT NULL,
    ExpectedReturnDate DATE NULL,
    ReturnDate         DATE NULL,
    Condition_         VARCHAR(50),
    CONSTRAINT fk_issued_request FOREIGN KEY (RequestID) REFERENCES Request(RequestID),
    CONSTRAINT fk_issued_employee FOREIGN KEY (EmployeeID) REFERENCES Employee(EmployeeID),
    CONSTRAINT fk_issued_asset FOREIGN KEY (AssetID) REFERENCES Asset(AssetID)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- AuditLog  (append-only; the application layer never issues UPDATE/DELETE)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS AuditLog (
    LogID     INT AUTO_INCREMENT PRIMARY KEY,
    UserID    INT NULL,
    Action    VARCHAR(150) NOT NULL,
    TableName VARCHAR(50),
    RecordID  INT,
    Timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (UserID) REFERENCES UserAccount(UserID) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- RememberToken  (supports the "Remember me" login option)
-- Selector/validator pattern: the selector is looked up directly,
-- the validator is only ever compared as a hash, so a stolen database
-- row alone cannot be replayed as a valid login cookie.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS RememberToken (
    TokenID        INT AUTO_INCREMENT PRIMARY KEY,
    UserID         INT NOT NULL,
    Selector       VARCHAR(24) NOT NULL UNIQUE,
    ValidatorHash  VARCHAR(255) NOT NULL,
    ExpiresAt      DATETIME NOT NULL,
    CreatedAt      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_remember_user FOREIGN KEY (UserID) REFERENCES UserAccount(UserID) ON DELETE CASCADE
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- Helpful indexes
CREATE INDEX idx_request_status ON Request(Status);
CREATE INDEX idx_request_employee ON Request(EmployeeID);
CREATE INDEX idx_asset_status ON Asset(Status);
CREATE INDEX idx_audit_timestamp ON AuditLog(Timestamp);
