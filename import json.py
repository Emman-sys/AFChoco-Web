import csv
import random
from datetime import datetime, timedelta

# 1. Setup Data & Products
products = [
    {"name": "Tobleron", "price": 149, "id": "3NbKvPK9euzNcCS71DFr"},
    {"name": "Dark Chocolate", "price": 126, "id": "DarkChoc01"},
    {"name": "White Chocolate", "price": 129, "id": "WhiteChoc02"},
    {"name": "Milk Chocolate", "price": 139, "id": "MilkChoc03"},
    {"name": "AF Candy Chocolate", "price": 12, "id": "AFCandy04"}
]

start_date = datetime(2023, 1, 1)
end_date = datetime(2026, 2, 15)
total_orders = 5000
growth_rate = 0.05  # 5% monthly increase

# 2. Calculate Growth Distribution
curr = start_date
months = []
while curr <= end_date:
    months.append(curr)
    if curr.month == 12: curr = curr.replace(year=curr.year + 1, month=1)
    else: curr = curr.replace(month=curr.month + 1)

weights = [(1 + growth_rate)**i for i in range(len(months))]
orders_per_month = [int((w / sum(weights)) * total_orders) for w in weights]

# 3. Generate and Save CSV
filename = "orders_dataset.csv"

with open(filename, mode='w', newline='', encoding='utf-8') as file:
    writer = csv.writer(file)
    
    # Schema Header
    writer.writerow([
        "orderId", "createdAt", "deliveryAddress", "deliveryFee", 
        "productId", "productName", "productPrice", "quantity", 
        "orderStatus", "paymentStatus", "subtotal", "totalAmount", 
        "userEmail", "userId"
    ])

    counter = 1
    for i, month_start in enumerate(months):
        for _ in range(orders_per_month[i]):
            prod = random.choice(products)
            qty = random.randint(1, 5)
            subtotal = prod['price'] * qty
            total = subtotal + 50
            
            # Create timestamp within the specific month
            day_offset = random.randint(0, 27)
            order_date = month_start + timedelta(
                days=day_offset, 
                hours=random.randint(0, 23), 
                minutes=random.randint(0, 59)
            )
            
            writer.writerow([
                f"DO630JkNFCFKKaX{counter:05d}",
                order_date.strftime("%Y-%m-%d %H:%M:%S"),
                "484 Taysan - Ibaan Rd, Ibaan, Batangas, Philippines",
                50,
                prod['id'],
                prod['name'],
                prod['price'],
                qty,
                "DELIVERED",
                "PAID",
                subtotal,
                total,
                f"user{random.randint(100, 999)}@gmail.com",
                "4Qw6l0ZqRLcjg0eDUg62NhtIAAk1"
            ])
            counter += 1

print(f"Done. Created {filename} with {counter-1} entries.")