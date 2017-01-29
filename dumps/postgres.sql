CREATE DATABASE "testdb";

\c testdb;

CREATE TABLE "music" (
    "id" BIGSERIAL PRIMARY KEY,
    "title" VARCHAR(255) NOT NULL,
    "artist" VARCHAR(255) NOT NULL,
    "duration" SMALLINT NOT NULL,
    "created_at" TIMESTAMP NULL
);
