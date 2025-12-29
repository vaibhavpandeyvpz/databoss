-- SQL Server initialization script
IF NOT EXISTS (SELECT * FROM sys.databases WHERE name = 'testdb')
BEGIN
    CREATE DATABASE testdb;
END
GO

USE testdb;
GO

IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'music')
BEGIN
    CREATE TABLE music (
        id INT IDENTITY(1,1) PRIMARY KEY,
        title NVARCHAR(255) NOT NULL,
        artist NVARCHAR(255) NOT NULL,
        duration INT NULL,
        created_at DATETIME2 DEFAULT GETDATE(),
        is_active BIT DEFAULT 1
    );
END
GO

