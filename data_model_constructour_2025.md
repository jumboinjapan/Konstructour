# Data Model (Constructour 2025, исправленная версия)

## Таблицы и сущности

### Clients
- id (string, PK)
- name (string)
- email (string)
- phone (string)
- preferences (json)

### Tours
- id (string, PK)
- clientId (FK -> Clients)
- name (string)
- startDate (date)
- endDate (date)
- status (enum: Draft, Active, Completed)
- budget (number)

### TourDays
- id (string, PK)
- tourId (FK -> Tours)
- date (date)
- regionId (FK -> Regions)
- cityId (FK -> Cities)
- lodgingId (FK -> Lodgings, optional)

### Regions
- id (string, PK)
- name (string)
- description (text)

### Cities
- id (string, PK)
- regionId (FK -> Regions)
- name (string)

### Locations
- id (string, PK)
- cityId (FK -> Cities)
- name (string)
- type (enum: Temple, Museum, Park, etc.)

### POIs (Points of Interest)
- id (string, PK)
- locationId (FK -> Locations)
- name (string)
- description (text)
- ticketTypeId (FK -> TicketTypes, optional)

### TicketTypes
- id (string, PK)
- poiId (FK -> POIs)
- category (enum: Adult, Child, Senior, Special)
- basePrice (number)
- priceCoefficient (float)

### Lodgings
- id (string, PK)
- cityId (FK -> Cities)
- name (string)
- type (enum: Hotel, Ryokan, Guesthouse)

## TypeScript пример

```ts
interface TicketType {
  id: string;
  category: "Adult" | "Child" | "Senior" | "Special";
  basePrice: number;
  priceCoefficient: number;
}

function calculateTickets(ticketType: TicketType, count: number) {
  return ticketType.basePrice * ticketType.priceCoefficient * count;
}
```